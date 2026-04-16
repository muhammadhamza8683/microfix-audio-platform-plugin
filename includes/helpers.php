<?php
/**
 * Helper Functions
 *
 * Reusable utility functions used across the plugin.
 *
 * @package MicrofixAudioPlatform
 */

defined( 'ABSPATH' ) || exit;

// ─── Secure URL Builders ──────────────────────────────────────────────────────

/**
 * Build the secure streaming URL for an audio file.
 *
 * @param int $file_id    Attachment ID of the audio file.
 * @param int $episode_id Post ID of the episode.
 *
 * @return string Signed secure stream URL.
 */
function microfix_get_secure_audio_url( int $file_id, int $episode_id ): string {
	if ( ! $file_id || ! $episode_id ) {
		return '';
	}

	return add_query_arg( [
		'microfix_secure_stream' => $file_id,
		'episode'                => $episode_id,
		'type'                   => 'audio',
		'token'                  => microfix_generate_stream_token( $file_id, $episode_id ),
	], home_url( '/' ) );
}

/**
 * Build the secure streaming URL for a video file.
 *
 * @param int $file_id    Attachment ID of the video file.
 * @param int $episode_id Post ID of the episode.
 *
 * @return string Signed secure stream URL.
 */
function microfix_get_secure_video_url( int $file_id, int $episode_id ): string {
	if ( ! $file_id || ! $episode_id ) {
		return '';
	}

	return add_query_arg( [
		'microfix_secure_stream' => $file_id,
		'episode'                => $episode_id,
		'type'                   => 'video',
		'token'                  => microfix_generate_stream_token( $file_id, $episode_id ),
	], home_url( '/' ) );
}

/**
 * Generate a short-lived HMAC token to sign a stream request.
 * Tokens expire after 2 hours.
 *
 * @param int $file_id    Attachment ID.
 * @param int $episode_id Episode post ID.
 *
 * @return string Hex token.
 */
function microfix_generate_stream_token( int $file_id, int $episode_id ): string {
	$user_id   = get_current_user_id();
	$window    = (int) ( time() / 7200 ); // 2-hour rolling window
	$secret    = wp_salt( 'auth' );
	$data      = "{$file_id}:{$episode_id}:{$user_id}:{$window}";

	return hash_hmac( 'sha256', $data, $secret );
}

/**
 * Validate a stream token received in a request.
 *
 * @param string $token     Token from query string.
 * @param int    $file_id   Attachment ID.
 * @param int    $episode_id Episode post ID.
 *
 * @return bool True if token matches current or previous window.
 */
function microfix_verify_stream_token( string $token, int $file_id, int $episode_id ): bool {
	if ( empty( $token ) ) {
		return false;
	}

	$user_id = get_current_user_id();
	$secret  = wp_salt( 'auth' );

	// Accept current window and the previous one to handle boundary edge cases.
	foreach ( [ 0, -1 ] as $offset ) {
		$window   = (int) ( time() / 7200 ) + $offset;
		$data     = "{$file_id}:{$episode_id}:{$user_id}:{$window}";
		$expected = hash_hmac( 'sha256', $data, $secret );

		if ( hash_equals( $expected, $token ) ) {
			return true;
		}
	}

	return false;
}

// ─── Episode Metadata Helpers ─────────────────────────────────────────────────

/**
 * Determine whether an episode is locked by its unlock_date.
 *
 * @param int $post_id Episode post ID.
 *
 * @return bool True when locked (date is in the future).
 */
function microfix_is_episode_locked( int $post_id ): bool {
	$unlock_date = get_field( 'unlock_date', $post_id );

	// No date set → never date-locked.
	if ( empty( $unlock_date ) ) {
		return false;
	}

	$unlock_ts = strtotime( $unlock_date );
	if ( false === $unlock_ts ) {
		return false;
	}

	return time() < $unlock_ts;
}

/**
 * Return the formatted unlock date label for a locked episode.
 *
 * @param int $post_id Episode post ID.
 *
 * @return string e.g. "Available on June 1, 2025"
 */
function microfix_get_unlock_label( int $post_id ): string {
	$unlock_date = get_field( 'unlock_date', $post_id );

	if ( empty( $unlock_date ) ) {
		return '';
	}

	$ts = strtotime( $unlock_date );
	if ( ! $ts ) {
		return '';
	}

	return sprintf(
		/* translators: %s: formatted date */
		__( 'Available on %s', 'microfix-audio-platform' ),
		date_i18n( get_option( 'date_format' ), $ts )
	);
}

/**
 * Get the content type of an episode: 'audio' or 'video'.
 *
 * @param int $post_id Episode post ID.
 *
 * @return string 'audio' | 'video'
 */
function microfix_get_episode_content_type( int $post_id ): string {
	$type = get_field( 'content_type', $post_id );
	return in_array( $type, [ 'audio', 'video' ], true ) ? $type : 'audio';
}

/**
 * Get all episodes belonging to a specific program, ordered by menu_order then date.
 *
 * @param int $program_id Program post ID.
 *
 * @return WP_Post[] Array of episode posts.
 */
function microfix_get_program_episodes( int $program_id ): array {
	$query = new WP_Query( [
		'post_type'      => 'episode',
		'posts_per_page' => -1,
		'orderby'        => [ 'menu_order' => 'ASC', 'date' => 'ASC' ],
		'meta_query'     => [
			[
				'key'     => 'program',
				'value'   => $program_id,
				'compare' => '=',
			],
		],
	] );

	return $query->posts;
}

/**
 * Get all programs ordered by their custom display order field, then date.
 *
 * @return WP_Post[] Array of program posts.
 */
function microfix_get_all_programs(): array {
	$query = new WP_Query( [
		'post_type'      => 'program',
		'posts_per_page' => -1,
		'orderby'        => 'meta_value_num date',
		'meta_key'       => 'program_order',
		'order'          => 'ASC',
	] );

	return $query->posts;
}

/**
 * Safely get the thumbnail URL for a post, with an optional fallback.
 *
 * @param int    $post_id  Post ID.
 * @param string $size     Image size slug.
 * @param string $fallback Fallback URL if no thumbnail exists.
 *
 * @return string Image URL.
 */
function microfix_get_thumbnail_url( int $post_id, string $size = 'medium', string $fallback = '' ): string {
	if ( has_post_thumbnail( $post_id ) ) {
		return (string) get_the_post_thumbnail_url( $post_id, $size );
	}

	return $fallback ?: MICROFIX_PLUGIN_URL . 'assets/images/placeholder.svg';
}
