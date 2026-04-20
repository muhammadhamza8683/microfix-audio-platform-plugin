<?php
/**
 * MemberPress — Add "Dashboard" to Account Navigation
 *
 * Adds a custom "Dashboard" link to the MemberPress account
 * sidebar navigation, positioned right after "My Profile".
 *
 * HOW TO USE:
 *  Option A) Paste this entire file into your theme's functions.php
 *  Option B) Add require_once 'mepr-account-nav.php'; in your plugin
 *  Option C) Copy just the two add_filter() calls into your plugin
 *
 * The dashboard page URL is filtered via 'microfix_dashboard_url'
 * so you can override it without touching this file.
 *
 * @package MicrofixAudioPlatform
 */

defined( 'ABSPATH' ) || exit;

// ─── 1. Register the custom nav item ─────────────────────────────────────────

add_filter( 'mepr-account-nav', 'microfix_add_dashboard_to_mepr_nav' );

/**
 * Inject the Dashboard link into MemberPress account navigation.
 *
 * MemberPress passes an ordered array of nav items. Each item is an array:
 *   'title'  => Display label
 *   'url'    => Full URL
 *   'class'  => CSS class on the <li>  (optional)
 *   'icon'   => dashicons class or SVG (optional, depends on theme)
 *
 * We splice it in at position 1 (after "My Profile" which is position 0).
 *
 * @param array $nav Existing nav items.
 * @return array Modified nav items.
 */
function microfix_add_dashboard_to_mepr_nav( array $nav ): array {

	$dashboard_url = apply_filters(
		'microfix_dashboard_url',
		home_url( '/dashboard/' )
	);

	$dashboard_item = [
		'title' => __( 'Dashboard', 'microfix-audio-platform' ),
		'url'   => $dashboard_url,
		'class' => 'mepr-account-dashboard',    // used for CSS targeting
	];

	// Splice in at index 1 — after "My Profile" (index 0).
	array_splice( $nav, 1, 0, [ $dashboard_item ] );

	return $nav;
}

// ─── 2. Mark nav item as active when on the dashboard page ───────────────────

add_filter( 'mepr-account-nav-item-class', 'microfix_mepr_nav_active_class', 10, 2 );

/**
 * Add an 'active' class to the Dashboard nav item when the user is
 * currently viewing the dashboard page.
 *
 * @param string $class  Current class string for the nav item.
 * @param array  $item   Nav item array (has 'url', 'title', 'class').
 * @return string
 */
function microfix_mepr_nav_active_class( string $class, array $item ): string {
	if ( empty( $item['class'] ) || $item['class'] !== 'mepr-account-dashboard' ) {
		return $class;
	}

	$current_url = ( is_ssl() ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

	if ( untrailingslashit( $current_url ) === untrailingslashit( home_url( '/dashboard/' ) ) ) {
		$class .= ' mepr-active current-menu-item';
	}

	return $class;
}

// ─── 3. Inject the nav icon via CSS ──────────────────────────────────────────
// MemberPress account nav icons are controlled purely via CSS using
// ::before pseudo-elements with a dashicon or SVG background.
// We output a small <style> block in wp_head targeting our custom class.

add_action( 'wp_head', 'microfix_mepr_nav_icon_styles' );

function microfix_mepr_nav_icon_styles(): void {
	// Only output on pages that show the MemberPress account nav.
	if ( ! function_exists( 'mepr_get_account_page_id' ) ) return;

	$account_page_id = mepr_get_account_page_id();
	if ( ! is_page( $account_page_id ) && ! is_page( 'dashboard' ) ) return;
	?>
	<style id="microfix-mepr-nav-icon">
		/*
		 * Dashboard icon in MemberPress account nav.
		 * Uses a grid/dashboard SVG as inline background.
		 * Adjust size/color to match your theme.
		 */
		.mepr-account-nav .mepr-account-dashboard a::before,
		.mepr-nav .mepr-account-dashboard a::before,
		li.mepr-account-dashboard > a::before {
			content: '';
			display : inline-block;
			width   : 16px;
			height  : 16px;
			margin-right: 8px;
			vertical-align: middle;
			flex-shrink: 0;

			/* Grid / dashboard icon (SVG, theme-color-safe) */
			background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='3' width='7' height='7'/%3E%3Crect x='14' y='3' width='7' height='7'/%3E%3Crect x='3' y='14' width='7' height='7'/%3E%3Crect x='14' y='14' width='7' height='7'/%3E%3C/svg%3E");
			background-size    : contain;
			background-repeat  : no-repeat;
			background-position: center;

			/*
			 * Tint the icon to match surrounding nav text color.
			 * If MemberPress uses colored icons, remove this filter.
			 */
			opacity: 0.75;
		}

		/* Active / current state */
		li.mepr-account-dashboard.mepr-active > a::before,
		li.mepr-account-dashboard.current-menu-item > a::before {
			opacity: 1;
		}

		li.mepr-account-dashboard.mepr-active > a,
		li.mepr-account-dashboard.current-menu-item > a {
			font-weight: 700;
		}
	</style>
	<?php
}
