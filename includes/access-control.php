<?php
/**
 * Access Control
 *
 * Handles all membership & drip-content gate logic.
 *
 * @package MicrofixAudioPlatform
 */

defined( 'ABSPATH' ) || exit;

// ─── Core Gate ────────────────────────────────────────────────────────────────

/**
 * Determine whether the current user can access a given episode.
 *
 * Access is granted only when ALL three conditions are met:
 *  1. User is logged in.
 *  2. User holds an active MemberPress membership.
 *  3. The episode's unlock_date (if set) has passed.
 *
 * @param int $post_id Episode post ID.
 *
 * @return bool True if access is allowed.
 */
function microfix_user_can_access_episode( int $post_id ): bool {
	// Gate 1 – Must be logged in.
	if ( ! is_user_logged_in() ) {
		return false;
	}

	// Gate 2 – Must have an active MemberPress membership.
	if ( ! microfix_has_active_membership() ) {
		return false;
	}

	// Gate 3 – Drip date must have passed.
	if ( microfix_is_episode_locked( $post_id ) ) {
		return false;
	}

	return true;
}

// ─── MemberPress Membership Check ─────────────────────────────────────────────

/**
 * Check whether the current user has at least one active MemberPress membership.
 *
 * Works with any subscription status that MemberPress considers "active":
 * active, trial, paused, complimentary, etc.
 *
 * @return bool True if the user is an active member.
 */
function microfix_has_active_membership(): bool {
	// Allow administrators to bypass the membership gate.
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}

	$user_id = get_current_user_id();

	// ── Strategy A: MemberPress 1.9+ — MeprUser class ──────────────────────────
	if ( class_exists( 'MeprUser' ) ) {
		try {
			$mepr_user    = new MeprUser( $user_id );
			$active_txns  = $mepr_user->active_product_subscriptions( 'ids' );

			return ! empty( $active_txns );
		} catch ( \Throwable $e ) {
			// Fall through to next strategy.
		}
	}

	// ── Strategy B: MemberPress legacy — mepr_is_user_active() helper ──────────
	if ( function_exists( 'mepr_is_user_active' ) ) {
		return (bool) mepr_is_user_active( $user_id );
	}

	// ── Strategy C: Check wp_usermeta for MemberPress capability ───────────────
	$user = get_userdata( $user_id );
	if ( $user instanceof WP_User ) {
		if ( in_array( 'mepr-active', (array) $user->roles, true ) ) {
			return true;
		}

		// MemberPress sometimes stores active status as user meta.
		$mepr_active = get_user_meta( $user_id, 'mepr_active', true );
		if ( ! empty( $mepr_active ) ) {
			return true;
		}
	}

	/**
	 * Filter: allow third-party extensions to override membership status.
	 *
	 * @param bool $has_access Default false when all strategies fail.
	 * @param int  $user_id    Current user ID.
	 */
	return (bool) apply_filters( 'microfix_has_active_membership', false, $user_id );
}

// ─── Access Denial Response ───────────────────────────────────────────────────

/**
 * Return a reason code explaining why a user cannot access an episode.
 * Used by shortcodes to show appropriate messaging.
 *
 * @param int $post_id Episode post ID.
 *
 * @return string  'not_logged_in' | 'no_membership' | 'date_locked' | 'ok'
 */
function microfix_get_access_denial_reason( int $post_id ): string {
	if ( ! is_user_logged_in() ) {
		return 'not_logged_in';
	}

	if ( ! microfix_has_active_membership() ) {
		return 'no_membership';
	}

	if ( microfix_is_episode_locked( $post_id ) ) {
		return 'date_locked';
	}

	return 'ok';
}

/**
 * Render a human-friendly access denial message block.
 *
 * @param int $post_id Episode post ID.
 *
 * @return string HTML string.
 */
function microfix_render_access_denied_message( int $post_id ): string {
	$reason = microfix_get_access_denial_reason( $post_id );

	switch ( $reason ) {
		case 'not_logged_in':
			$message = sprintf(
				'<a href="%s" class="mfx-login-link">%s</a>',
				esc_url( wp_login_url( get_permalink( $post_id ) ) ),
				esc_html__( 'Log in to access this content', 'microfix-audio-platform' )
			);
			break;

		case 'no_membership':
			/**
			 * Filter: URL to redirect users without membership.
			 *
			 * @param string $url     Default membership page URL.
			 * @param int    $post_id Episode post ID.
			 */
			$membership_url = apply_filters(
				'microfix_membership_page_url',
				home_url( '/membership/' ),
				$post_id
			);

			$message = sprintf(
				'<a href="%s" class="mfx-upgrade-link">%s</a>',
				esc_url( $membership_url ),
				esc_html__( 'Get access — upgrade your membership', 'microfix-audio-platform' )
			);
			break;

		case 'date_locked':
			$label   = microfix_get_unlock_label( $post_id );
			$message = esc_html( $label ?: __( 'This episode is not yet available.', 'microfix-audio-platform' ) );
			break;

		default:
			return '';
	}

	return sprintf(
		'<div class="mfx-access-denied mfx-access-denied--%s" role="alert">%s</div>',
		esc_attr( $reason ),
		$message
	);
}
