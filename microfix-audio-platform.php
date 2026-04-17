<?php
/**
 * Plugin Name:     Microfix Audio Platform
 * Plugin URI:      https://microfix.com
 * Description:     Membership-based audio & video course platform. Secure streaming, drip content, weekly sessions, member dashboard, Elementor shortcodes.
 * Version:         2.0.0
 * Author:          Microfix
 * Author URI:      https://microfix.com
 * Text Domain:     microfix-audio-platform
 * Requires PHP:    8.0
 * Requires at least: 6.3
 *
 * @package MicrofixAudioPlatform
 */

defined( 'ABSPATH' ) || exit;

// ─── Constants ────────────────────────────────────────────────────────────────

define( 'MICROFIX_VERSION',     '2.0.0' );
define( 'MICROFIX_PLUGIN_FILE', __FILE__ );
define( 'MICROFIX_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'MICROFIX_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

// ─── Autoload Modules ─────────────────────────────────────────────────────────

require_once MICROFIX_PLUGIN_DIR . 'includes/helpers.php';
require_once MICROFIX_PLUGIN_DIR . 'includes/post-types.php';
require_once MICROFIX_PLUGIN_DIR . 'includes/access-control.php';
require_once MICROFIX_PLUGIN_DIR . 'includes/secure-stream.php';
require_once MICROFIX_PLUGIN_DIR . 'includes/progress-tracker.php';
require_once MICROFIX_PLUGIN_DIR . 'includes/shortcodes.php';
require_once MICROFIX_PLUGIN_DIR . 'includes/elementor-widgets.php';

// ─── Frontend Assets ──────────────────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', 'microfix_enqueue_assets' );

function microfix_enqueue_assets(): void {
	wp_enqueue_style(
		'microfix-styles',
		MICROFIX_PLUGIN_URL . 'assets/css/styles.css',
		[],
		MICROFIX_VERSION
	);

	wp_enqueue_script(
		'microfix-player',
		MICROFIX_PLUGIN_URL . 'assets/js/player.js',
		[],
		MICROFIX_VERSION,
		true
	);

	wp_localize_script( 'microfix-player', 'MicrofixConfig', [
		'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
		'nonce'      => wp_create_nonce( 'microfix_player' ),
		'progressNonce' => wp_create_nonce( 'microfix_progress' ),
		'siteUrl'    => home_url(),
		'isLoggedIn' => is_user_logged_in(),
		'userId'     => get_current_user_id(),
	] );
}

// ─── AJAX: Save playback progress ─────────────────────────────────────────────

add_action( 'wp_ajax_microfix_save_progress', 'microfix_ajax_save_progress' );

function microfix_ajax_save_progress(): void {
	check_ajax_referer( 'microfix_progress', 'nonce' );

	$episode_id = absint( $_POST['episode_id'] ?? 0 );
	$position   = floatval( $_POST['position']   ?? 0 );
	$duration   = floatval( $_POST['duration']   ?? 0 );

	if ( ! $episode_id || ! is_user_logged_in() ) {
		wp_send_json_error( 'Invalid request' );
	}

	microfix_save_progress( get_current_user_id(), $episode_id, $position, $duration );
	wp_send_json_success();
}

// ─── REST API: expose thumbnail URL on episode endpoint ───────────────────────
// Used by the JS player bar to show the episode thumbnail after play starts.

add_action( 'rest_api_init', 'microfix_register_rest_fields' );

function microfix_register_rest_fields(): void {
	register_rest_field( 'episode', 'mfx_thumbnail_url', [
		'get_callback' => function ( $post_arr ) {
			$url = get_the_post_thumbnail_url( $post_arr['id'], 'thumbnail' );
			return $url ?: '';
		},
		'schema' => [
			'description' => 'Episode thumbnail URL (thumbnail size).',
			'type'        => 'string',
			'context'     => [ 'view' ],
		],
	] );

	// Also expose episode duration for the bar display.
	register_rest_field( 'episode', 'mfx_duration', [
		'get_callback' => function ( $post_arr ) {
			return (string) ( get_field( 'duration', $post_arr['id'] ) ?: '' );
		},
		'schema' => [
			'description' => 'Episode duration string.',
			'type'        => 'string',
			'context'     => [ 'view' ],
		],
	] );
}

// ─── Admin: Episode list — show Program + Unlock Date columns ─────────────────

add_filter( 'manage_episode_posts_columns', 'microfix_episode_admin_columns' );
add_action( 'manage_episode_posts_custom_column', 'microfix_episode_admin_column_content', 10, 2 );
add_filter( 'manage_edit-episode_sortable_columns', 'microfix_episode_sortable_columns' );

function microfix_episode_admin_columns( array $columns ): array {
	$new = [];
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( $key === 'title' ) {
			$new['mfx_program']     = __( 'Program', 'microfix-audio-platform' );
			$new['mfx_unlock_date'] = __( 'Unlock Date', 'microfix-audio-platform' );
			$new['mfx_status']      = __( 'Status', 'microfix-audio-platform' );
		}
	}
	return $new;
}

function microfix_episode_admin_column_content( string $column, int $post_id ): void {
	switch ( $column ) {
		case 'mfx_program':
			$program_id = (int) get_field( 'program', $post_id );
			if ( $program_id ) {
				printf(
					'<a href="%s">%s</a>',
					esc_url( get_edit_post_link( $program_id ) ),
					esc_html( get_the_title( $program_id ) )
				);
			} else {
				echo '<span style="color:#999">—</span>';
			}
			break;

		case 'mfx_unlock_date':
			$date = get_field( 'unlock_date', $post_id );
			if ( $date ) {
				$ts      = strtotime( $date );
				$is_past = $ts < time();
				printf(
					'<span style="color:%s">%s</span>',
					$is_past ? '#46b450' : '#d54e21',
					esc_html( date_i18n( get_option( 'date_format' ), $ts ) )
				);
			} else {
				echo '<span style="color:#46b450">' . esc_html__( 'Always', 'microfix-audio-platform' ) . '</span>';
			}
			break;

		case 'mfx_status':
			$type    = microfix_get_episode_content_type( $post_id );
			$has_file = $type === 'video'
				? (bool) get_field( 'video_file', $post_id ) || (bool) get_field( 'video_url', $post_id )
				: (bool) get_field( 'audio_file', $post_id );
			if ( $has_file ) {
				echo '<span style="color:#46b450">✓ ' . esc_html( ucfirst( $type ) ) . '</span>';
			} else {
				echo '<span style="color:#d54e21">⚠ No file</span>';
			}
			break;
	}
}

function microfix_episode_sortable_columns( array $columns ): array {
	$columns['mfx_unlock_date'] = 'mfx_unlock_date';
	return $columns;
}

// Handle sorting by unlock_date in admin.
add_action( 'pre_get_posts', function ( WP_Query $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) return;
	if ( $query->get( 'post_type' ) !== 'episode' ) return;
	if ( $query->get( 'orderby' ) === 'mfx_unlock_date' ) {
		$query->set( 'meta_key', 'unlock_date' );
		$query->set( 'orderby',  'meta_value' );
	}
} );

// ─── Activation / Deactivation ────────────────────────────────────────────────

register_activation_hook( __FILE__, 'microfix_activate' );
register_deactivation_hook( __FILE__, 'microfix_deactivate' );

function microfix_activate(): void {
	microfix_register_post_types();
	microfix_create_progress_table();
	flush_rewrite_rules();
}

function microfix_deactivate(): void {
	flush_rewrite_rules();
}
