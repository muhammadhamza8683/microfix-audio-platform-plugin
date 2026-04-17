<?php
/**
 * Access Control
 *
 * @package MicrofixAudioPlatform
 */

defined( 'ABSPATH' ) || exit;

/**
 * Core gate: can current user access an episode?
 */
function microfix_user_can_access_episode( int $post_id ): bool {
	if ( ! is_user_logged_in() )          return false;
	if ( ! microfix_has_active_membership() ) return false;
	if ( microfix_is_episode_locked( $post_id ) ) return false;
	return true;
}

/**
 * Check active MemberPress membership.
 * Tries 3 strategies to cover all MemberPress versions.
 */
function microfix_has_active_membership(): bool {
	// Admins always pass.
	if ( current_user_can( 'manage_options' ) ) return true;

	$user_id = get_current_user_id();

	// Strategy A — MeprUser class (MemberPress 1.9+)
	if ( class_exists( 'MeprUser' ) ) {
		try {
			$mepr_user   = new MeprUser( $user_id );
			$active_subs = $mepr_user->active_product_subscriptions( 'ids' );
			if ( ! empty( $active_subs ) ) return true;
		} catch ( \Throwable $e ) {}
	}

	// Strategy B — legacy helper
	if ( function_exists( 'mepr_is_user_active' ) && mepr_is_user_active( $user_id ) ) {
		return true;
	}

	// Strategy C — user meta fallback
	$meta = get_user_meta( $user_id, 'mepr_active', true );
	if ( ! empty( $meta ) ) return true;

	return (bool) apply_filters( 'microfix_has_active_membership', false, $user_id );
}

/**
 * Reason code for access denial.
 * Returns: 'ok' | 'not_logged_in' | 'no_membership' | 'date_locked'
 */
function microfix_get_access_denial_reason( int $post_id ): string {
	if ( ! is_user_logged_in() )              return 'not_logged_in';
	if ( ! microfix_has_active_membership() ) return 'no_membership';
	if ( microfix_is_episode_locked( $post_id ) ) return 'date_locked';
	return 'ok';
}

/**
 * HTML access denied message block.
 */
function microfix_render_access_denied_message( int $post_id ): string {
	$reason = microfix_get_access_denial_reason( $post_id );
	switch ( $reason ) {
		case 'not_logged_in':
			$msg = sprintf(
				'<a href="%s" class="mfx-link">%s</a>',
				esc_url( wp_login_url( get_permalink( $post_id ) ) ),
				esc_html__( 'Log in to access', 'microfix-audio-platform' )
			);
			break;
		case 'no_membership':
			$url = apply_filters( 'microfix_membership_page_url', home_url( '/membership/' ), $post_id );
			$msg = sprintf(
				'<a href="%s" class="mfx-link">%s</a>',
				esc_url( $url ),
				esc_html__( 'Upgrade membership', 'microfix-audio-platform' )
			);
			break;
		case 'date_locked':
			$label = microfix_get_unlock_label( $post_id );
			$msg   = esc_html( $label ?: __( 'Not yet available', 'microfix-audio-platform' ) );
			break;
		default:
			return '';
	}
	return '<span class="mfx-access-msg mfx-access-msg--' . esc_attr( $reason ) . '">' . $msg . '</span>';
}
