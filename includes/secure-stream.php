<?php
/**
 * Secure Media Streaming
 *
 * Serves audio and video files via a PHP endpoint, preventing
 * direct access to /wp-content/uploads/ URLs.
 *
 * RECOMMENDED .htaccess rule (add to /wp-content/uploads/.htaccess):
 * ───────────────────────────────────────────────────────────────────
 *   # Block direct access to audio/video files
 *   <FilesMatch "\.(mp3|mp4|ogg|wav|aac|webm|m4a|m4v)$">
 *     Order Allow,Deny
 *     Deny from all
 *   </FilesMatch>
 * ───────────────────────────────────────────────────────────────────
 * Nginx equivalent (add inside server block):
 *   location ~* ^/wp-content/uploads/.*\.(mp3|mp4|ogg|wav|aac|webm|m4a|m4v)$ {
 *     deny all;
 *   }
 *
 * @package MicrofixAudioPlatform
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'microfix_handle_secure_stream_request', 1 );

/**
 * Intercept /?microfix_secure_stream=FILE_ID&episode=POST_ID&type=audio|video&token=TOKEN
 * and stream the file if the user is authorised.
 */
function microfix_handle_secure_stream_request(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( empty( $_GET['microfix_secure_stream'] ) ) {
		return;
	}

	$file_id    = absint( $_GET['microfix_secure_stream'] );
	$episode_id = absint( $_GET['episode'] ?? 0 );
	$type       = sanitize_key( $_GET['type'] ?? 'audio' );
	$token      = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	// ── Validate inputs ────────────────────────────────────────────────────────

	if ( ! $file_id || ! $episode_id ) {
		microfix_stream_error( 400, 'Bad Request' );
	}

	// ── Validate HMAC token ────────────────────────────────────────────────────

	if ( ! microfix_verify_stream_token( $token, $file_id, $episode_id ) ) {
		microfix_stream_error( 403, 'Invalid or expired token' );
	}

	// ── Validate login ─────────────────────────────────────────────────────────

	if ( ! is_user_logged_in() ) {
		microfix_stream_error( 401, 'Authentication required' );
	}

	// ── Validate MemberPress access + drip date ────────────────────────────────

	if ( ! microfix_user_can_access_episode( $episode_id ) ) {
		microfix_stream_error( 403, 'Access denied' );
	}

	// ── Resolve file path ──────────────────────────────────────────────────────

	$file_path = get_attached_file( $file_id );

	if ( ! $file_path || ! file_exists( $file_path ) ) {
		microfix_stream_error( 404, 'File not found' );
	}

	// Double-check the attachment actually belongs to the episode.
	$audio_field = get_field( 'audio_file', $episode_id );
	$video_field = get_field( 'video_file', $episode_id );

	$allowed_ids = array_filter( [ (int) $audio_field, (int) $video_field ] );

	if ( ! in_array( $file_id, $allowed_ids, true ) ) {
		microfix_stream_error( 403, 'File not associated with this episode' );
	}

	// ── Detect MIME type ───────────────────────────────────────────────────────

	$mime = microfix_resolve_mime_type( $file_path, $type );

	// ── Stream the file ────────────────────────────────────────────────────────

	microfix_stream_file( $file_path, $mime );
}

// ─── Streaming Engine ─────────────────────────────────────────────────────────

/**
 * Stream a file to the client with range-request support.
 *
 * Supports partial content (byte ranges) so HTML5 players can seek freely.
 *
 * @param string $file_path Absolute filesystem path.
 * @param string $mime      MIME type string.
 *
 * @return never
 */
function microfix_stream_file( string $file_path, string $mime ): never {
	// Prevent any PHP buffering from interfering.
	while ( ob_get_level() ) {
		ob_end_clean();
	}

	$file_size = filesize( $file_path );
	$start     = 0;
	$end       = $file_size - 1;
	$status    = 200;

	// ── Handle Range Requests ──────────────────────────────────────────────────
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
	$range_header = $_SERVER['HTTP_RANGE'] ?? '';

	if ( $range_header ) {
		if ( preg_match( '/bytes=(\d+)-(\d*)/', $range_header, $matches ) ) {
			$start = (int) $matches[1];
			if ( ! empty( $matches[2] ) ) {
				$end = (int) $matches[2];
			}

			if ( $start > $end || $start >= $file_size || $end >= $file_size ) {
				header( 'HTTP/1.1 416 Range Not Satisfiable' );
				header( "Content-Range: bytes */{$file_size}" );
				exit;
			}

			$status = 206;
		}
	}

	$length = ( $end - $start ) + 1;

	// ── Emit headers ───────────────────────────────────────────────────────────
	header( "HTTP/1.1 {$status} " . ( 206 === $status ? 'Partial Content' : 'OK' ) );
	header( 'Content-Type: ' . $mime );
	header( "Content-Length: {$length}" );
	header( 'Accept-Ranges: bytes' );
	header( "Content-Range: bytes {$start}-{$end}/{$file_size}" );

	// Prevent WordPress / Nginx from buffering the output.
	header( 'X-Accel-Buffering: no' );

	// Security: no caching of protected content.
	header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
	header( 'Pragma: no-cache' );

	// Prevent content-type sniffing.
	header( 'X-Content-Type-Options: nosniff' );

	// Force inline rather than download.
	$filename = basename( $file_path );
	header( "Content-Disposition: inline; filename=\"{$filename}\"" );

	// ── Send file bytes ────────────────────────────────────────────────────────
	$fp = fopen( $file_path, 'rb' );
	if ( ! $fp ) {
		microfix_stream_error( 500, 'Could not open file' );
	}

	fseek( $fp, $start );

	$bytes_remaining = $length;
	$chunk_size      = 8192; // 8 KB chunks

	while ( ! feof( $fp ) && $bytes_remaining > 0 && ! connection_aborted() ) {
		$read = min( $chunk_size, $bytes_remaining );
		echo fread( $fp, $read ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		flush();
		$bytes_remaining -= $read;
	}

	fclose( $fp );
	exit;
}

// ─── MIME Resolution ──────────────────────────────────────────────────────────

/**
 * Resolve the correct MIME type for a file.
 *
 * @param string $file_path Absolute path to file.
 * @param string $type      'audio' or 'video' hint.
 *
 * @return string MIME type string.
 */
function microfix_resolve_mime_type( string $file_path, string $type ): string {
	$ext_map = [
		// Audio
		'mp3'  => 'audio/mpeg',
		'ogg'  => 'audio/ogg',
		'wav'  => 'audio/wav',
		'm4a'  => 'audio/mp4',
		'aac'  => 'audio/aac',
		// Video
		'mp4'  => 'video/mp4',
		'webm' => 'video/webm',
		'm4v'  => 'video/mp4',
		'ogv'  => 'video/ogg',
	];

	$ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

	if ( isset( $ext_map[ $ext ] ) ) {
		return $ext_map[ $ext ];
	}

	// Fallback based on type hint.
	return 'video' === $type ? 'video/mp4' : 'audio/mpeg';
}

// ─── Error Response ───────────────────────────────────────────────────────────

/**
 * Terminate the request with an HTTP error.
 *
 * @param int    $code    HTTP status code.
 * @param string $message Human-readable message.
 *
 * @return never
 */
function microfix_stream_error( int $code, string $message ): never {
	status_header( $code );
	header( 'Content-Type: text/plain; charset=utf-8' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo esc_html( $message );
	exit;
}
