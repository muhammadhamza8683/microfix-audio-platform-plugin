<?php
/**
 * Playback Progress Tracker
 *
 * Stores per-user episode playback position in a custom DB table.
 * Used for "Continue Listening" on the dashboard.
 *
 * Table: {prefix}microfix_progress
 * Columns: id, user_id, episode_id, position (seconds), duration (seconds), updated_at
 *
 * @package MicrofixAudioPlatform
 */

defined( 'ABSPATH' ) || exit;

// ─── Table Setup ──────────────────────────────────────────────────────────────

function microfix_create_progress_table(): void {
	global $wpdb;
	$table   = $wpdb->prefix . 'microfix_progress';
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS {$table} (
		id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id    BIGINT(20) UNSIGNED NOT NULL,
		episode_id BIGINT(20) UNSIGNED NOT NULL,
		position   FLOAT NOT NULL DEFAULT 0,
		duration   FLOAT NOT NULL DEFAULT 0,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY user_episode (user_id, episode_id),
		KEY user_id (user_id),
		KEY updated_at (updated_at)
	) {$charset};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

// ─── Save Progress ────────────────────────────────────────────────────────────

function microfix_save_progress( int $user_id, int $episode_id, float $position, float $duration ): void {
	global $wpdb;
	$table = $wpdb->prefix . 'microfix_progress';

	$wpdb->replace( $table, [
		'user_id'    => $user_id,
		'episode_id' => $episode_id,
		'position'   => round( $position, 2 ),
		'duration'   => round( $duration, 2 ),
		'updated_at' => current_time( 'mysql' ),
	], [ '%d', '%d', '%f', '%f', '%s' ] );
}

// ─── Get Progress ─────────────────────────────────────────────────────────────

/**
 * Get saved playback position for a user+episode.
 *
 * @return array{position: float, duration: float, percent: float}|null
 */
function microfix_get_progress( int $user_id, int $episode_id ): ?array {
	global $wpdb;
	$table = $wpdb->prefix . 'microfix_progress';

	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT position, duration FROM {$table} WHERE user_id = %d AND episode_id = %d",
		$user_id, $episode_id
	), ARRAY_A );

	if ( ! $row ) return null;

	$pos = (float) $row['position'];
	$dur = (float) $row['duration'];

	return [
		'position' => $pos,
		'duration' => $dur,
		'percent'  => $dur > 0 ? round( ( $pos / $dur ) * 100 ) : 0,
	];
}

/**
 * Get the most recently played episode for a user (for "Continue Listening").
 *
 * Returns the episode post + progress data, or null if none.
 *
 * @return array{episode: WP_Post, progress: array}|null
 */
function microfix_get_continue_listening( int $user_id ): ?array {
	global $wpdb;
	$table = $wpdb->prefix . 'microfix_progress';

	// Get the last updated row that is not fully completed (< 95%).
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT episode_id, position, duration
		 FROM {$table}
		 WHERE user_id = %d
		   AND position > 0
		   AND ( duration = 0 OR (position / duration) < 0.95 )
		 ORDER BY updated_at DESC
		 LIMIT 5",
		$user_id
	), ARRAY_A );

	if ( empty( $rows ) ) return null;

	foreach ( $rows as $row ) {
		$post = get_post( (int) $row['episode_id'] );
		if ( ! $post || $post->post_type !== 'episode' || $post->post_status !== 'publish' ) {
			continue;
		}
		return [
			'episode'  => $post,
			'progress' => [
				'position' => (float) $row['position'],
				'duration' => (float) $row['duration'],
				'percent'  => (float) $row['duration'] > 0
					? round( ( (float) $row['position'] / (float) $row['duration'] ) * 100 )
					: 0,
			],
		];
	}

	return null;
}
