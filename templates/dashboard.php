<?php
/**
 * Member Dashboard Template
 *
 * Variables available from shortcode:
 *  $user            WP_User
 *  $user_id         int
 *  $is_member       bool
 *  $this_week_eps   WP_Post[]
 *  $hero_episode    WP_Post|null
 *  $continue        array{episode, progress}|null
 *  $all_programs    WP_Post[]
 *  $new_this_week   int
 *
 * @package MicrofixAudioPlatform
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="mfx-dashboard" id="mfx-dashboard">

	<!-- ── Header ────────────────────────────────────────────────── -->
	<div class="mfx-dashboard__header">
		<div class="mfx-dashboard__welcome">
			<h1 class="mfx-dashboard__greeting">
				<?php
				$hour = (int) current_time( 'G' );
				if ( $hour < 12 )      $greeting = __( 'Good morning', 'microfix-audio-platform' );
				elseif ( $hour < 17 )  $greeting = __( 'Good afternoon', 'microfix-audio-platform' );
				else                   $greeting = __( 'Good evening', 'microfix-audio-platform' );
				echo esc_html( $greeting . ', ' . $user->display_name );
				?>
			</h1>
			<p class="mfx-dashboard__subtitle">
				<?php esc_html_e( 'Continue building your emotional intelligence', 'microfix-audio-platform' ); ?>
			</p>
		</div>

		<?php if ( $new_this_week > 0 ) : ?>
		<div class="mfx-dashboard__new-badge">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
			<?php echo esc_html( sprintf(
				_n( '%d new episode unlocks this week', '%d new episodes unlock this week', $new_this_week, 'microfix-audio-platform' ),
				$new_this_week
			) ); ?>
		</div>
		<?php endif; ?>
	</div>

	<?php if ( ! $is_member ) : ?>
	<!-- ── Membership gate ───────────────────────────────────────── -->
	<div class="mfx-dashboard__gate">
		<p><?php esc_html_e( 'You need an active membership to access content.', 'microfix-audio-platform' ); ?></p>
		<a href="<?php echo esc_url( apply_filters( 'microfix_membership_page_url', home_url( '/membership/' ), 0 ) ); ?>" class="mfx-btn mfx-btn--primary">
			<?php esc_html_e( 'Get Access', 'microfix-audio-platform' ); ?>
		</a>
	</div>

	<?php else :

	// Find any episode this week that is still future-locked by date.
	$all_this_week = microfix_get_this_week_episodes( 5 );
	$next_drop     = null;
	foreach ( $all_this_week as $_ep ) {
		if ( microfix_is_episode_locked( $_ep->ID ) ) { $next_drop = $_ep; break; }
	}
	?>

	<?php if ( $next_drop ) : ?>
	<div class="mfx-next-drop-notice">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
		<span>
			<strong><?php echo esc_html( get_the_title( $next_drop->ID ) ); ?></strong>
			<?php echo ' — ' . esc_html( microfix_get_unlock_label( $next_drop->ID ) ); ?>
		</span>
	</div>
	<?php endif; ?>

	<!-- ── This Week's Episode (hero) ────────────────────────────── -->
	<?php if ( $hero_episode ) :
		$h_id       = $hero_episode->ID;
		$h_title    = get_the_title( $h_id );
		$h_duration = get_field( 'duration', $h_id );
		$h_thumb    = microfix_get_thumbnail_url( $h_id, 'large' );
		$h_gradient = microfix_get_card_gradient( $h_id );
		$h_week     = microfix_get_episode_week_label( $h_id );
		$h_date     = get_field( 'unlock_date', $h_id );
		$h_access   = microfix_user_can_access_episode( $h_id );
	?>
	<div class="mfx-dashboard__section-label"><?php esc_html_e( "This Week's Episode", 'microfix-audio-platform' ); ?></div>

	<div class="mfx-hero-episode"
		style="<?php echo $h_thumb ? 'background-image:url(' . esc_url( $h_thumb ) . ')' : 'background:' . esc_attr( $h_gradient ); ?>">

		<div class="mfx-hero-episode__overlay"></div>

		<div class="mfx-hero-episode__content">
			<div class="mfx-hero-episode__top">
				<?php if ( $h_week ) : ?>
				<span class="mfx-hero-episode__week"><?php echo esc_html( $h_week ); ?></span>
				<?php endif; ?>
				<?php if ( $h_date ) : ?>
				<span class="mfx-hero-episode__date"><?php echo esc_html( date_i18n( 'F j, Y', strtotime( $h_date ) ) ); ?></span>
				<?php endif; ?>
			</div>

			<h2 class="mfx-hero-episode__title"><?php echo esc_html( $h_title ); ?></h2>

			<div class="mfx-hero-episode__meta">
				<?php if ( $h_duration ) : ?>
				<span><?php echo esc_html( $h_duration ); ?></span>
				<?php endif; ?>
			</div>

			<div class="mfx-hero-episode__action">
				<?php echo do_shortcode( '[mfx_play_button episode_id="' . $h_id . '"]' ); ?>
			</div>
		</div>

	</div>
	<?php endif; ?>

	<!-- ── Continue Listening ─────────────────────────────────────── -->
	<?php if ( $continue ) :
		$cl_ep   = $continue['episode'];
		$cl_prog = $continue['progress'];
		$cl_id   = $cl_ep->ID;
		$cl_thumb = microfix_get_thumbnail_url( $cl_id, 'medium' );
		$cl_grad  = microfix_get_card_gradient( $cl_id );
		$cl_dur   = get_field( 'duration', $cl_id );
		$cl_date  = get_field( 'unlock_date', $cl_id );
	?>
	<div class="mfx-dashboard__section-label"><?php esc_html_e( 'Continue Listening', 'microfix-audio-platform' ); ?></div>

	<div class="mfx-continue-card">
		<div class="mfx-continue-card__thumb"
			style="<?php echo $cl_thumb ? 'background-image:url(' . esc_url( $cl_thumb ) . ')' : 'background:' . esc_attr( $cl_grad ); ?>">
			<div class="mfx-continue-card__thumb-play">
				<?php echo do_shortcode( '[mfx_play_button episode_id="' . $cl_id . '"]' ); ?>
			</div>
		</div>

		<div class="mfx-continue-card__body">
			<h4 class="mfx-continue-card__title"><?php echo esc_html( get_the_title( $cl_id ) ); ?></h4>
			<div class="mfx-continue-card__meta">
				<?php if ( $cl_date ) : ?>
				<span><?php echo esc_html( date_i18n( 'F j, Y', strtotime( $cl_date ) ) ); ?></span>
				<?php endif; ?>
				<?php if ( $cl_dur ) : ?>
				<span><?php echo esc_html( $cl_dur ); ?></span>
				<?php endif; ?>
			</div>
			<div class="mfx-continue-card__progress-wrap">
				<div class="mfx-continue-card__progress">
					<div class="mfx-continue-card__progress-fill" style="width:<?php echo esc_attr( $cl_prog['percent'] ); ?>%"></div>
				</div>
				<span class="mfx-continue-card__pct"><?php echo esc_html( $cl_prog['percent'] . '%' ); ?></span>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<!-- ── All Episodes ──────────────────────────────────────────── -->
	<div class="mfx-dashboard__section-label"><?php esc_html_e( 'All Episodes', 'microfix-audio-platform' ); ?></div>

	<?php if ( empty( $all_programs ) ) : ?>
	<p class="mfx-empty-state"><?php esc_html_e( 'No episodes have been published yet.', 'microfix-audio-platform' ); ?></p>
	<?php else : ?>

	<div class="mfx-all-episodes">
		<?php foreach ( $all_programs as $program ) :
			$program_id = $program->ID;
			$episodes   = microfix_get_program_episodes( $program_id );
			if ( empty( $episodes ) ) continue;
		?>
		<div class="mfx-program-block">
			<h3 class="mfx-program-block__title"><?php echo esc_html( get_the_title( $program_id ) ); ?></h3>
			<div class="mfx-ep-grid mfx-ep-grid--cols-3">
				<?php foreach ( $episodes as $ep ) :
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo microfix_render_episode_card( $ep->ID );
				endforeach; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<?php endif; // end all programs ?>
	<?php endif; // end is_member ?>

</div><!-- .mfx-dashboard -->

<?php echo microfix_render_global_player_shell(); // phpcs:ignore ?>
