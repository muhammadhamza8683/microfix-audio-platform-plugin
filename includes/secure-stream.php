<?php
/**
 * Secure Media Streaming
 *
 * Endpoint: /?microfix_secure_stream=FILE_ID&episode=POST_ID&type=audio|video&token=TOKEN
 *
 * RECOMMENDED .htaccess rule (add to /wp-content/uploads/.htaccess):
 * ───────────────────────────────────────────────────────────────────
 *   <FilesMatch "\.(mp3|mp4|ogg|wav|aac|webm|m4a|m4v)$">
 *     Order Allow,Deny
 *     Deny from all
 *   </FilesMatch>
 * ───────────────────────────────────────────────────────────────────
 *
 * @package MicrofixAudioPlatform
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'microfix_handle_secure_stream_request', 1 );

function microfix_handle_secure_stream_request(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( empty( $_GET['microfix_secure_stream'] ) ) return;

	$file_id    = absint( $_GET['microfix_secure_stream'] );
	$episode_id = absint( $_GET['episode'] ?? 0 );
	$type       = sanitize_key( $_GET['type'] ?? 'audio' );
	$token      = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
	// phpcs:enable

	if ( ! $file_id || ! $episode_id )                                   microfix_stream_error( 400, 'Bad Request' );
	if ( ! microfix_verify_stream_token( $token, $file_id, $episode_id ) ) microfix_stream_error( 403, 'Invalid or expired token' );
	if ( ! is_user_logged_in() )                                          microfix_stream_error( 401, 'Authentication required' );
	if ( ! microfix_user_can_access_episode( $episode_id ) )              microfix_stream_error( 403, 'Access denied' );

	$file_path = get_attached_file( $file_id );
	if ( ! $file_path || ! file_exists( $file_path ) )                   microfix_stream_error( 404, 'File not found' );

	// Confirm file belongs to this episode.
	$allowed = array_filter( [
		(int) get_field( 'audio_file', $episode_id ),
		(int) get_field( 'video_file', $episode_id ),
	] );
	if ( ! in_array( $file_id, $allowed, true ) )                         microfix_stream_error( 403, 'File not associated with episode' );

	microfix_stream_file( $file_path, microfix_resolve_mime_type( $file_path, $type ) );
}

// ─── Streaming Engine ─────────────────────────────────────────────────────────

function microfix_stream_file( string $file_path, string $mime ): never {
	while ( ob_get_level() ) ob_end_clean();

	$file_size = filesize( $file_path );
	$start     = 0;
	$end       = $file_size - 1;
	$status    = 200;

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
	$range = $_SERVER['HTTP_RANGE'] ?? '';
	if ( $range && preg_match( '/bytes=(\d+)-(\d*)/', $range, $m ) ) {
		$start = (int) $m[1];
		if ( ! empty( $m[2] ) ) $end = (int) $m[2];
		if ( $start > $end || $start >= $file_size || $end >= $file_size ) {
			header( 'HTTP/1.1 416 Range Not Satisfiable' );
			header( "Content-Range: bytes */{$file_size}" );
			exit;
		}
		$status = 206;
	}

	$length = ( $end - $start ) + 1;

	header( "HTTP/1.1 {$status} " . ( 206 === $status ? 'Partial Content' : 'OK' ) );
	header( 'Content-Type: ' . $mime );
	header( "Content-Length: {$length}" );
	header( 'Accept-Ranges: bytes' );
	header( "Content-Range: bytes {$start}-{$end}/{$file_size}" );
	header( 'X-Accel-Buffering: no' );
	header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
	header( 'Pragma: no-cache' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Content-Disposition: inline; filename="' . basename( $file_path ) . '"' );

	$fp = fopen( $file_path, 'rb' );
	if ( ! $fp ) microfix_stream_error( 500, 'Could not open file' );

	fseek( $fp, $start );
	$remaining = $length;
	while ( ! feof( $fp ) && $remaining > 0 && ! connection_aborted() ) {
		$read = min( 8192, $remaining );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo fread( $fp, $read );
		flush();
		$remaining -= $read;
	}
	fclose( $fp );
	exit;
}

function microfix_resolve_mime_type( string $file_path, string $type ): string {
	$map = [
		'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav',
		'm4a' => 'audio/mp4',  'aac' => 'audio/aac',
		'mp4' => 'video/mp4',  'webm' => 'video/webm', 'm4v' => 'video/mp4', 'ogv' => 'video/ogg',
	];
	$ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
	return $map[ $ext ] ?? ( 'video' === $type ? 'video/mp4' : 'audio/mpeg' );
}

function microfix_stream_error( int $code, string $message ): never {
	status_header( $code );
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo esc_html( $message );
	exit;
}
