<?php
/**
 * MemberPress — Add "Dashboard" to Account Navigation
 *
 * Compatible with MemberPress ReadyLaunch theme (current default).
 *
 * The ReadyLaunch nav fires:
 *   do_action( 'mepr_account_nav', $mepr_user )  — inject extra <li> items here
 *
 * We hook at priority 5 so our item appears right after "My Profile"
 * (which MemberPress renders before firing the action).
 *
 * @package MicrofixAudioPlatform
 */

defined( 'ABSPATH' ) || exit;

// ─── 1. Output the Dashboard <li> inside the account nav ─────────────────────

add_action( 'mepr_account_nav', 'microfix_render_dashboard_nav_item', 5 );

/**
 * Echo the Dashboard nav item HTML.
 * MemberPress ReadyLaunch fires this action inside the <ul> and expects <li> output.
 *
 * @param mixed $mepr_user MeprUser object passed by MemberPress (not used here).
 */
function microfix_render_dashboard_nav_item( $mepr_user ): void {

	$dashboard_url = apply_filters( 'microfix_dashboard_url', home_url( '/dashboard/' ) );

	// Compare URL paths to detect active state — no $_SERVER hostname needed.
	$current_path   = isset( $_SERVER['REQUEST_URI'] )
		? parse_url( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH )
		: '';
	$dashboard_path = parse_url( $dashboard_url, PHP_URL_PATH );
	$is_active      = rtrim( $current_path, '/' ) === rtrim( $dashboard_path, '/' );
	$active_class   = $is_active ? ' mepr-active current-menu-item' : '';

	// Inline SVG grid icon — inherits CSS currentColor automatically.
	$icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
		stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
		width="16" height="16" aria-hidden="true" focusable="false" class="mepr-nav-icon">
		<rect x="3" y="3" width="7" height="7" rx="1"/>
		<rect x="14" y="3" width="7" height="7" rx="1"/>
		<rect x="3" y="14" width="7" height="7" rx="1"/>
		<rect x="14" y="14" width="7" height="7" rx="1"/>
	</svg>';

	echo '<li class="mepr-account-nav-item mepr-account-dashboard' . esc_attr( $active_class ) . '">';
	echo '<a href="' . esc_url( $dashboard_url ) . '">';
	echo $icon; // Safe: hardcoded SVG, no user input.
	echo '<span>' . esc_html__( 'Dashboard', 'microfix-audio-platform' ) . '</span>';
	echo '</a>';
	echo '</li>';
}

// ─── 2. Scoped CSS for icon alignment + active state ─────────────────────────

add_action( 'wp_head', 'microfix_mepr_nav_styles' );

function microfix_mepr_nav_styles(): void {
	if ( ! function_exists( 'mepr_get_account_page_id' ) ) return;

	// Only output styles when MemberPress account UI is on screen.
	if ( ! is_page( mepr_get_account_page_id() ) && ! is_page( 'dashboard' ) ) return;
	?>
	<style id="microfix-mepr-nav">
		/* Microfix: Dashboard nav item — ReadyLaunch compatible */

		.mepr-account-dashboard a {
			display    : flex;
			align-items: center;
			gap        : 8px;
		}

		.mepr-account-dashboard .mepr-nav-icon {
			flex-shrink: 0;
			opacity    : 0.6;
			transition : opacity 0.15s ease;
		}

		.mepr-account-dashboard:hover .mepr-nav-icon,
		.mepr-account-dashboard.mepr-active .mepr-nav-icon,
		.mepr-account-dashboard.current-menu-item .mepr-nav-icon {
			opacity: 1;
		}

		.mepr-account-dashboard.mepr-active > a,
		.mepr-account-dashboard.current-menu-item > a {
			font-weight: 600;
		}
	</style>
	<?php
}
