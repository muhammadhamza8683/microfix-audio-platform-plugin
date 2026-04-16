<?php
/**
 * Plugin Name:     Microfix Audio Platform
 * Plugin URI:      https://microfix.com
 * Description:     Membership-based audio & video course platform with secure streaming, drip content, and Elementor shortcodes.
 * Version:         1.0.0
 * Author:          Microfix
 * Author URI:      https://microfix.com
 * Text Domain:     microfix-audio-platform
 * Requires PHP:    8.0
 * Requires at least: 6.0
 *
 * @package MicrofixAudioPlatform
 */

defined( 'ABSPATH' ) || exit;

// ─── Constants ────────────────────────────────────────────────────────────────

define( 'MICROFIX_VERSION',     '1.0.0' );
define( 'MICROFIX_PLUGIN_FILE', __FILE__ );
define( 'MICROFIX_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'MICROFIX_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

// ─── Autoload Modules ─────────────────────────────────────────────────────────

require_once MICROFIX_PLUGIN_DIR . 'includes/helpers.php';
require_once MICROFIX_PLUGIN_DIR . 'includes/post-types.php';
require_once MICROFIX_PLUGIN_DIR . 'includes/access-control.php';
require_once MICROFIX_PLUGIN_DIR . 'includes/secure-stream.php';
require_once MICROFIX_PLUGIN_DIR . 'includes/shortcodes.php';

// ─── Bootstrap ────────────────────────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', 'microfix_enqueue_assets' );

/**
 * Enqueue frontend assets.
 */
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

	// Pass AJAX URL and nonce to JS for future extensibility.
	wp_localize_script( 'microfix-player', 'MicrofixConfig', [
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'microfix_player' ),
		'siteUrl' => home_url(),
	] );
}

// ─── Activation / Deactivation ────────────────────────────────────────────────

register_activation_hook( __FILE__, 'microfix_activate' );
register_deactivation_hook( __FILE__, 'microfix_deactivate' );

/**
 * Plugin activation callback.
 * Registers CPTs then flushes rewrite rules.
 */
function microfix_activate(): void {
	microfix_register_post_types();
	flush_rewrite_rules();
}

/**
 * Plugin deactivation callback.
 */
function microfix_deactivate(): void {
	flush_rewrite_rules();
}
