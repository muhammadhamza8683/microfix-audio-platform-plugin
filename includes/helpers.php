<?php
/**
 * Helper Functions
 *
 * @package MicrofixAudioPlatform
 */

defined( 'ABSPATH' ) || exit;

// ─── Secure URL Builders ──────────────────────────────────────────────────────

function microfix_get_secure_audio_url( int $file_id, int $episode_id ): string {
	if ( ! $file_id || ! $episode_id ) return '';
	return add_query_arg( [
		'microfix_secure_stream' => $file_id,
		'episode'                => $episode_id,
		'type'                   => 'audio',
		'token'                  => microfix_generate_stream_token( $file_id, $episode_id ),
	], home_url( '/' ) );
}

function microfix_get_secure_video_url( int $file_id, int $episode_id ): string {
	if ( ! $file_id || ! $episode_id ) return '';
	return add_query_arg( [
		'microfix_secure_stream' => $file_id,
		'episode'                => $episode_id,
		'type'                   => 'video',
		'token'                  => microfix_generate_stream_token( $file_id, $episode_id ),
	], home_url( '/' ) );
}

function microfix_generate_stream_token( int $file_id, int $episode_id ): string {
	$user_id = get_current_user_id();
	$window  = (int) ( time() / 7200 );
	$secret  = wp_salt( 'auth' );
	return hash_hmac( 'sha256', "{$file_id}:{$episode_id}:{$user_id}:{$window}", $secret );
}

function microfix_verify_stream_token( string $token, int $file_id, int $episode_id ): bool {
	if ( empty( $token ) ) return false;
	$user_id = get_current_user_id();
	$secret  = wp_salt( 'auth' );
	foreach ( [ 0, -1 ] as $offset ) {
		$window   = (int) ( time() / 7200 ) + $offset;
		$expected = hash_hmac( 'sha256', "{$file_id}:{$episode_id}:{$user_id}:{$window}", $secret );
		if ( hash_equals( $expected, $token ) ) return true;
	}
	return false;
}

// ─── Episode State Helpers ────────────────────────────────────────────────────

/**
 * Is the episode date-locked (unlock_date is in the future)?
 */
function microfix_is_episode_locked( int $post_id ): bool {
	$unlock_date = get_field( 'unlock_date', $post_id );
	if ( empty( $unlock_date ) ) return false;
	$ts = strtotime( $unlock_date );
	return $ts && time() < $ts;
}

/**
 * Human-readable unlock label, e.g. "Available on April 22, 2025"
 */
function microfix_get_unlock_label( int $post_id ): string {
	$unlock_date = get_field( 'unlock_date', $post_id );
	if ( empty( $unlock_date ) ) return '';
	$ts = strtotime( $unlock_date );
	if ( ! $ts ) return '';
	return sprintf(
		__( 'Available on %s', 'microfix-audio-platform' ),
		date_i18n( get_option( 'date_format' ), $ts )
	);
}

/**
 * Week label for an episode based on unlock_date, e.g. "Week 3"
 * Calculated relative to the earliest unlock_date among all episodes in the same program.
 */
function microfix_get_episode_week_label( int $post_id ): string {
	$unlock_date = get_field( 'unlock_date', $post_id );
	if ( empty( $unlock_date ) ) return '';

	$program_id = (int) get_field( 'program', $post_id );
	if ( ! $program_id ) return '';

	// Find earliest unlock_date in the same program.
	$episodes = microfix_get_program_episodes( $program_id );
	$earliest = null;

	foreach ( $episodes as $ep ) {
		$d = get_field( 'unlock_date', $ep->ID );
		if ( empty( $d ) ) continue;
		$ts = strtotime( $d );
		if ( $earliest === null || $ts < $earliest ) {
			$earliest = $ts;
		}
	}

	if ( ! $earliest ) return '';

	$ep_ts    = strtotime( $unlock_date );
	$diff_days = (int) floor( ( $ep_ts - $earliest ) / DAY_IN_SECONDS );
	$week_num  = (int) floor( $diff_days / 7 ) + 1;

	return sprintf( __( 'Week %d', 'microfix-audio-platform' ), $week_num );
}

/**
 * Content type: 'audio' or 'video'
 */
function microfix_get_episode_content_type( int $post_id ): string {
	$type = get_field( 'content_type', $post_id );
	return in_array( $type, [ 'audio', 'video' ], true ) ? $type : 'audio';
}

/**
 * Get the episode's primary category term (first assigned).
 */
function microfix_get_episode_category( int $post_id ): ?WP_Term {
	$terms = get_the_terms( $post_id, 'episode_category' );
	if ( empty( $terms ) || is_wp_error( $terms ) ) return null;
	return $terms[0];
}

// ─── Week Detection ───────────────────────────────────────────────────────────

/**
 * Get the Monday timestamp for the current week.
 */
function microfix_get_current_week_monday(): int {
	$now      = current_time( 'timestamp' );
	$day_of_w = (int) date( 'N', $now ); // 1=Mon … 7=Sun
	$monday   = $now - ( ( $day_of_w - 1 ) * DAY_IN_SECONDS );
	return (int) strtotime( date( 'Y-m-d 00:00:00', $monday ) );
}

/**
 * Get the Sunday timestamp for the current week.
 */
function microfix_get_current_week_sunday(): int {
	return microfix_get_current_week_monday() + ( 6 * DAY_IN_SECONDS ) + DAY_IN_SECONDS - 1;
}

/**
 * Does an episode's unlock_date fall within the current Mon–Sun week?
 */
function microfix_episode_is_this_week( int $post_id ): bool {
	$unlock_date = get_field( 'unlock_date', $post_id );
	if ( empty( $unlock_date ) ) return false;
	$ts = strtotime( $unlock_date );
	if ( ! $ts ) return false;
	return $ts >= microfix_get_current_week_monday() && $ts <= microfix_get_current_week_sunday();
}

// ─── Query Helpers ────────────────────────────────────────────────────────────

function microfix_get_program_episodes( int $program_id ): array {
	/*
	 * We do a two-pass approach so that:
	 *  - Episodes WITH an unlock_date are sorted by that date ASC
	 *  - Episodes WITHOUT an unlock_date come first (immediately available), sorted by menu_order
	 * This avoids WP_Query issues with optional meta_key sorting.
	 */
	$base_args = [
		'post_type'      => 'episode',
		'posts_per_page' => -1,
		'meta_query'     => [
			[
				'key'     => 'program',
				'value'   => $program_id,
				'compare' => '=',
			],
		],
	];

	// Pass 1: episodes without unlock_date (always available)
	$args_no_date = array_merge( $base_args, [
		'orderby'    => 'menu_order date',
		'order'      => 'ASC',
		'meta_query' => [
			'relation' => 'AND',
			[
				'key'     => 'program',
				'value'   => $program_id,
				'compare' => '=',
			],
			[
				'key'     => 'unlock_date',
				'value'   => '',
				'compare' => '=',
			],
		],
	] );

	// Pass 2: episodes with unlock_date, sorted ascending
	$args_with_date = array_merge( $base_args, [
		'orderby'    => 'meta_value date',
		'meta_key'   => 'unlock_date',
		'order'      => 'ASC',
		'meta_query' => [
			'relation' => 'AND',
			[
				'key'     => 'program',
				'value'   => $program_id,
				'compare' => '=',
			],
			[
				'key'     => 'unlock_date',
				'value'   => '',
				'compare' => '!=',
			],
		],
	] );

	$no_date   = ( new WP_Query( $args_no_date ) )->posts;
	$with_date = ( new WP_Query( $args_with_date ) )->posts;

	return array_merge( $no_date, $with_date );
}

function microfix_get_all_programs(): array {
	return ( new WP_Query( [
		'post_type'      => 'program',
		'posts_per_page' => -1,
		'orderby'        => 'menu_order date',
		'order'          => 'ASC',
	] ) )->posts;
}

/**
 * Get episodes that unlock this week (Mon–Sun), across all programs.
 *
 * @param int $limit Max episodes to return.
 */
function microfix_get_this_week_episodes( int $limit = 10 ): array {
	$monday = date( 'Y-m-d', microfix_get_current_week_monday() );
	$sunday = date( 'Y-m-d', microfix_get_current_week_sunday() );

	$query = new WP_Query( [
		'post_type'      => 'episode',
		'posts_per_page' => $limit,
		'orderby'        => 'meta_value',
		'meta_key'       => 'unlock_date',
		'order'          => 'ASC',
		'meta_query'     => [
			[
				'key'     => 'unlock_date',
				'value'   => [ $monday, $sunday ],
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			],
		],
	] );
	return $query->posts;
}

// ─── Thumbnail Helper ─────────────────────────────────────────────────────────

function microfix_get_thumbnail_url( int $post_id, string $size = 'medium', string $fallback = '' ): string {
	if ( has_post_thumbnail( $post_id ) ) {
		return (string) get_the_post_thumbnail_url( $post_id, $size );
	}
	return $fallback ?: MICROFIX_PLUGIN_URL . 'assets/images/placeholder.svg';
}

// ─── Gradient Generator ───────────────────────────────────────────────────────

/**
 * Return a deterministic gradient CSS string for an episode card
 * when no thumbnail is available. Cycles through a set of attractive gradients.
 */
function microfix_get_card_gradient( int $post_id ): string {
	$gradients = [
		'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
		'linear-gradient(135deg, #11998e 0%, #38ef7d 100%)',
		'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
		'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
		'linear-gradient(135deg, #f7971e 0%, #ffd200 100%)',
		'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
		'linear-gradient(135deg, #30cfd0 0%, #330867 100%)',
		'linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%)',
	];
	return $gradients[ $post_id % count( $gradients ) ];
}
