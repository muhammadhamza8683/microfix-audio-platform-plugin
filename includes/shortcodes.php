<?php
/**
 * Shortcodes
 *
 * List of all registered shortcodes:
 *
 *  [mfx_weekly_sessions]             – "Your Weekly Sessions" cards for homepage
 *  [mfx_member_dashboard]            – Full portal dashboard page
 *  [mfx_play_button episode_id=""]   – Standalone play/lock button
 *  [mfx_membership_status]           – Member status badge
 *  [mfx_episodes_grid program_id=""] – Grid of all episodes grouped by program
 *  [mfx_programs_grid]               – Program catalog
 *
 * @package MicrofixAudioPlatform
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'microfix_register_shortcodes' );

function microfix_register_shortcodes(): void {
	add_shortcode( 'mfx_weekly_sessions',   'microfix_sc_weekly_sessions' );
	add_shortcode( 'mfx_member_dashboard',  'microfix_sc_member_dashboard' );
	add_shortcode( 'mfx_small_play_button', 'microfix_sc_small_play_button' );
	add_shortcode( 'mfx_play_button',       'microfix_sc_play_button' );
	add_shortcode( 'mfx_membership_status', 'microfix_sc_membership_status' );
	add_shortcode( 'mfx_episodes_grid',     'microfix_sc_episodes_grid' );
	add_shortcode( 'mfx_programs_grid',     'microfix_sc_programs_grid' );
}

// ─────────────────────────────────────────────────────────────────────────────
// [mfx_weekly_sessions]
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Homepage "Your Weekly Sessions" section.
 * Shows episodes whose unlock_date falls within the current Mon–Sun week.
 * Non-members see gradient cards with a lock icon overlay.
 *
 * Attributes:
 *  limit (int)  – max cards to show (default 3)
 *  title (str)  – section heading (default "Your Weekly Sessions")
 *  subtitle (str) – subtitle text
 */
function microfix_sc_weekly_sessions( $atts ): string {
	$atts = shortcode_atts( [
		'limit'    => 3,
		'title'    => __( 'Your Weekly Sessions', 'microfix-audio-platform' ),
		'subtitle' => __( 'New episodes every Tuesday at 5am EST', 'microfix-audio-platform' ),
	], $atts );

	$episodes   = microfix_get_this_week_episodes( (int) $atts['limit'] );
	$can_access = microfix_has_active_membership();

	ob_start();
	?>
	<div class="mfx-weekly-sessions">

		<div class="mfx-weekly-sessions__header">
			<h2 class="mfx-weekly-sessions__title"><?php echo esc_html( $atts['title'] ); ?></h2>
			<?php if ( $atts['subtitle'] ) : ?>
			<p class="mfx-weekly-sessions__subtitle"><?php echo esc_html( $atts['subtitle'] ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( empty( $episodes ) ) : ?>
		<p class="mfx-empty-state"><?php esc_html_e( 'No sessions scheduled for this week. Check back soon!', 'microfix-audio-platform' ); ?></p>
		<?php else : ?>

		<div class="mfx-weekly-sessions__grid">
			<?php foreach ( $episodes as $episode ) :
				$ep_id      = $episode->ID;
				$thumb      = microfix_get_thumbnail_url( $ep_id, 'large' );
				$gradient   = microfix_get_card_gradient( $ep_id );
				$week_label = microfix_get_episode_week_label( $ep_id );
				$duration   = get_field( 'duration', $ep_id );
				$type       = microfix_get_episode_content_type( $ep_id );
				$category   = microfix_get_episode_category( $ep_id );
				$locked     = ! $can_access || microfix_is_episode_locked( $ep_id );
				$program_id = (int) get_field( 'program', $ep_id );
				$subtitle_text = get_field( 'subtitle', $ep_id ) ?: get_the_excerpt( $ep_id );
				?>
			<div class="mfx-session-card<?php echo $locked ? ' mfx-session-card--locked' : ''; ?>"
				data-episode-id="<?php echo esc_attr( $ep_id ); ?>">

				<!-- Card Background -->
				<div class="mfx-session-card__bg"
					style="<?php echo $thumb ? 'background-image:url(' . esc_url( $thumb ) . ')' : 'background:' . esc_attr( $gradient ); ?>">
				</div>

				<!-- Top badges -->
				<div class="mfx-session-card__top">
					<?php if ( $week_label ) : ?>
					<span class="mfx-session-card__week-badge"><?php echo esc_html( $week_label ); ?></span>
					<?php endif; ?>

					<?php if ( $duration ) : ?>
					<span class="mfx-session-card__duration"><?php echo esc_html( $duration ); ?></span>
					<?php endif; ?>
				</div>

				<!-- Lock overlay -->
				<?php if ( $locked ) : ?>
				<div class="mfx-session-card__lock-overlay" aria-label="<?php esc_attr_e( 'Locked content', 'microfix-audio-platform' ); ?>">
					<div class="mfx-session-card__lock-icon">
						<?php if ( microfix_is_episode_locked( $ep_id ) ) : ?>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
						<?php else : ?>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
						<?php endif; ?>
					</div>
				</div>
				<?php endif; ?>

				<!-- Bottom content -->
				<div class="mfx-session-card__body">
					<?php if ( $category ) : ?>
					<span class="mfx-session-card__category"><?php echo esc_html( $category->name ); ?></span>
					<?php endif; ?>

					<h3 class="mfx-session-card__title"><?php echo esc_html( get_the_title( $ep_id ) ); ?></h3>

					<?php if ( $subtitle_text ) : ?>
					<p class="mfx-session-card__desc"><?php echo esc_html( wp_trim_words( $subtitle_text, 10 ) ); ?></p>
					<?php endif; ?>

					<?php if ( ! $locked ) : ?>
					<div class="mfx-session-card__action">
						<?php echo do_shortcode( '[mfx_play_button episode_id="' . $ep_id . '"]' ); ?>
					</div>
					<?php else : ?>
					<div class="mfx-session-card__action">
						<?php echo microfix_render_access_denied_message( $ep_id ); // phpcs:ignore ?>
					</div>
					<?php endif; ?>
				</div>

			</div>
			<?php endforeach; ?>
		</div>

		<div class="mfx-weekly-sessions__footer">
			<a href="<?php echo esc_url( get_post_type_archive_link( 'episode' ) ); ?>" class="mfx-text-link">
				<?php esc_html_e( 'View all episodes →', 'microfix-audio-platform' ); ?>
			</a>
		</div>

		<?php endif; ?>
	</div>

	<?php echo microfix_render_global_player_shell(); // phpcs:ignore ?>
	<?php
	return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────────────────────
// [mfx_member_dashboard]
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Full member portal/dashboard.
 *
 * Sections:
 *  1. Welcome header + new episodes notice
 *  2. This Week's Episode (hero card)
 *  3. Continue Listening (last in-progress episode)
 *  4. All Episodes grid grouped by program, with category badges
 */
function microfix_sc_member_dashboard( $atts ): string {
	$atts = shortcode_atts( [], $atts );

	if ( ! is_user_logged_in() ) {
		return '<div class="mfx-dashboard-gate">'
			. '<p>' . esc_html__( 'Please log in to access your dashboard.', 'microfix-audio-platform' ) . '</p>'
			. '<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" class="mfx-btn mfx-btn--primary">' . esc_html__( 'Log In', 'microfix-audio-platform' ) . '</a>'
			. '</div>';
	}

	$user_id          = get_current_user_id();
	$user             = wp_get_current_user();
	$is_member        = microfix_has_active_membership();
	$this_week_eps    = microfix_get_this_week_episodes( 1 ); // hero = first this week
	$hero_episode     = ! empty( $this_week_eps ) ? $this_week_eps[0] : null;
	$continue         = $is_member ? microfix_get_continue_listening( $user_id ) : null;
	$all_programs     = microfix_get_all_programs();
	$new_this_week    = count( microfix_get_this_week_episodes( 10 ) );

	ob_start();
	include MICROFIX_PLUGIN_DIR . 'templates/dashboard.php';
	return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────────────────────
// [mfx_play_button]
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Renders a play button or locked indicator for a single episode.
 *
 * Attributes:
 *  episode_id (int) – defaults to current post
 */
function microfix_sc_play_button( $atts ): string {
	$atts = shortcode_atts( [ 'episode_id' => get_the_ID() ], $atts );
	$ep_id = absint( $atts['episode_id'] );
	if ( ! $ep_id || get_post_type( $ep_id ) !== 'episode' ) return '';

	$can_access = microfix_user_can_access_episode( $ep_id );

	if ( ! $can_access ) {
		$reason = microfix_get_access_denial_reason( $ep_id );
		$icon = 'date_locked' === $reason
			? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>'
			: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 019.9-1"/></svg>';

		return '<span class="mfx-btn-locked">'
			. $icon
			. '<span>' . esc_html( microfix_get_unlock_label( $ep_id ) ?: __( 'Locked', 'microfix-audio-platform' ) ) . '</span>'
			. '</span>';
	}

	$type  = microfix_get_episode_content_type( $ep_id );
	$title = get_the_title( $ep_id );

	if ( 'video' === $type ) {
		$vid_id = (int) get_field( 'video_file', $ep_id );
		if ( $vid_id ) {
			$stream = microfix_get_secure_video_url( $vid_id, $ep_id );
			$dtype  = 'video';
		} else {
			$stream = (string) get_field( 'video_url', $ep_id );
			$dtype  = 'external-video';
		}
	} else {
		$audio_id = (int) get_field( 'audio_file', $ep_id );
		if ( ! $audio_id ) {
			return '<span class="mfx-btn-locked">' . esc_html__( 'No media', 'microfix-audio-platform' ) . '</span>';
		}
		$stream = microfix_get_secure_audio_url( $audio_id, $ep_id );
		$dtype  = 'audio';
	}

	$progress = is_user_logged_in()
		? microfix_get_progress( get_current_user_id(), $ep_id )
		: null;

	return sprintf(
		'<button class="mfx-btn-play play-media"
			data-stream="%s"
			data-type="%s"
			data-episode="%d"
			data-title="%s"
			data-resume="%s"
			aria-label="%s">
			<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16" aria-hidden="true"><polygon points="5,3 19,12 5,21"/></svg>
			<span>%s</span>
		</button>',
		esc_url( $stream ),
		esc_attr( $dtype ),
		$ep_id,
		esc_attr( $title ),
		esc_attr( $progress ? (string) $progress['position'] : '0' ),
		esc_attr( sprintf( __( 'Play %s', 'microfix-audio-platform' ), $title ) ),
		esc_html( $progress && $progress['percent'] > 5 ? __( 'Resume', 'microfix-audio-platform' ) : __( 'Play Now', 'microfix-audio-platform' ) )
	);
}

// ─────────────────────────────────────────────────────────────────────────────
// [mfx_small_play_button]
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Renders a play button or locked indicator for a single episode.
 *
 * Attributes:
 *  episode_id (int) – defaults to current post
 */
function microfix_sc_small_play_button( $atts ): string {
	$atts = shortcode_atts( [ 'episode_id' => get_the_ID() ], $atts );
	$ep_id = absint( $atts['episode_id'] );
	if ( ! $ep_id || get_post_type( $ep_id ) !== 'episode' ) return '';

	$can_access = microfix_user_can_access_episode( $ep_id );

	if ( ! $can_access ) {
		$reason = microfix_get_access_denial_reason( $ep_id );
		$icon = 'date_locked' === $reason
			? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>'
			: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 019.9-1"/></svg>';

		return '<span class="mfx-btn-locked">'
			. $icon
			. '<span>' . esc_html( microfix_get_unlock_label( $ep_id ) ?: __( 'Locked', 'microfix-audio-platform' ) ) . '</span>'
			. '</span>';
	}

	$type  = microfix_get_episode_content_type( $ep_id );
	$title = get_the_title( $ep_id );

	if ( 'video' === $type ) {
		$vid_id = (int) get_field( 'video_file', $ep_id );
		if ( $vid_id ) {
			$stream = microfix_get_secure_video_url( $vid_id, $ep_id );
			$dtype  = 'video';
		} else {
			$stream = (string) get_field( 'video_url', $ep_id );
			$dtype  = 'external-video';
		}
	} else {
		$audio_id = (int) get_field( 'audio_file', $ep_id );
		if ( ! $audio_id ) {
			return '<span class="mfx-btn-locked">' . esc_html__( 'No media', 'microfix-audio-platform' ) . '</span>';
		}
		$stream = microfix_get_secure_audio_url( $audio_id, $ep_id );
		$dtype  = 'audio';
	}

	$progress = is_user_logged_in()
		? microfix_get_progress( get_current_user_id(), $ep_id )
		: null;

	return sprintf(
		'<button class="mfx-btn-small-play play-media"
			data-stream="%s"
			data-type="%s"
			data-episode="%d"
			data-title="%s"
			data-resume="%s"
			aria-label="%s">
			<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
				<path d="M6 3L20 12L6 21V3Z" stroke="#002D8B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</button>',
		esc_url( $stream ),
		esc_attr( $dtype ),
		$ep_id,
		esc_attr( $title ),
		esc_attr( $progress ? (string) $progress['position'] : '0' ),
		esc_attr( sprintf( __( 'Play %s', 'microfix-audio-platform' ), $title ) ),
		esc_html( $progress && $progress['percent'] > 5 ? __( 'Resume', 'microfix-audio-platform' ) : __( 'Play Now', 'microfix-audio-platform' ) )
	);
}

// ─────────────────────────────────────────────────────────────────────────────
// [mfx_membership_status]
// ─────────────────────────────────────────────────────────────────────────────

function microfix_sc_membership_status( $atts ): string {
	ob_start();
	if ( ! is_user_logged_in() ) {
		$login = wp_login_url( get_permalink() );
		echo '<div class="mfx-status mfx-status--guest">';
		echo '<span>' . esc_html__( 'Not logged in', 'microfix-audio-platform' ) . '</span>';
		echo '<a href="' . esc_url( $login ) . '" class="mfx-btn mfx-btn--primary">' . esc_html__( 'Log In', 'microfix-audio-platform' ) . '</a>';
		echo '</div>';
	} elseif ( microfix_has_active_membership() ) {
		echo '<div class="mfx-status mfx-status--active">';
		echo '<span class="mfx-status__dot mfx-status__dot--green"></span>';
		echo '<span>' . esc_html__( 'Active Member', 'microfix-audio-platform' ) . '</span>';
		echo '</div>';
	} else {
		$url = apply_filters( 'microfix_membership_page_url', home_url( '/membership/' ), 0 );
		echo '<div class="mfx-status mfx-status--inactive">';
		echo '<span class="mfx-status__dot mfx-status__dot--red"></span>';
		echo '<span>' . esc_html__( 'No active membership', 'microfix-audio-platform' ) . '</span>';
		echo '<a href="' . esc_url( $url ) . '" class="mfx-btn mfx-btn--primary">' . esc_html__( 'Get Access', 'microfix-audio-platform' ) . '</a>';
		echo '</div>';
	}
	return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────────────────────
// [mfx_episodes_grid]
// ─────────────────────────────────────────────────────────────────────────────

function microfix_sc_episodes_grid( $atts ): string {
	$atts = shortcode_atts( [
		'program_id' => 0,
		'columns'    => 3,
	], $atts );

	$cols     = max( 1, min( 4, (int) $atts['columns'] ) );
	$programs = (int) $atts['program_id']
		? array_filter( [ get_post( (int) $atts['program_id'] ) ] )
		: microfix_get_all_programs();

	ob_start();
	echo '<div class="mfx-all-episodes">';

	foreach ( $programs as $program ) {
		$program_id = $program->ID;
		$episodes   = microfix_get_program_episodes( $program_id );
		if ( empty( $episodes ) ) continue;
		echo '<div class="mfx-program-block">';
		echo '<h3 class="mfx-program-block__title">' . esc_html( get_the_title( $program_id ) ) . '</h3>';
		echo '<div class="mfx-ep-grid mfx-ep-grid--cols-' . esc_attr( $cols ) . '">';
		foreach ( $episodes as $ep ) {
			echo microfix_render_episode_card( $ep->ID ); // phpcs:ignore
		}
		echo '</div></div>';
	}

	echo '</div>';
	echo microfix_render_global_player_shell(); // phpcs:ignore
	return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────────────────────
// [mfx_programs_grid]
// ─────────────────────────────────────────────────────────────────────────────

function microfix_sc_programs_grid( $atts ): string {
	$atts     = shortcode_atts( [ 'columns' => 3 ], $atts );
	$cols     = max( 1, min( 4, (int) $atts['columns'] ) );
	$programs = microfix_get_all_programs();

	ob_start();
	echo '<div class="mfx-programs-grid mfx-programs-grid--cols-' . esc_attr( $cols ) . '">';

	foreach ( $programs as $program ) {
		$pid   = $program->ID;
		$thumb = microfix_get_thumbnail_url( $pid, 'medium' );
		$count = count( microfix_get_program_episodes( $pid ) );
		$desc  = get_field( 'program_description', $pid ) ?: get_the_excerpt( $pid );
		?>
		<article class="mfx-program-card">
			<?php if ( $thumb ) : ?>
			<div class="mfx-program-card__img">
				<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( get_the_title( $pid ) ); ?>" loading="lazy">
			</div>
			<?php endif; ?>
			<div class="mfx-program-card__body">
				<h3 class="mfx-program-card__title"><?php echo esc_html( get_the_title( $pid ) ); ?></h3>
				<?php if ( $desc ) : ?>
				<p class="mfx-program-card__desc"><?php echo esc_html( $desc ); ?></p>
				<?php endif; ?>
				<div class="mfx-program-card__footer">
					<span class="mfx-badge"><?php echo esc_html( sprintf( _n( '%d Episode', '%d Episodes', $count, 'microfix-audio-platform' ), $count ) ); ?></span>
					<a href="<?php echo esc_url( get_permalink( $pid ) ); ?>" class="mfx-btn mfx-btn--outline"><?php esc_html_e( 'View →', 'microfix-audio-platform' ); ?></a>
				</div>
			</div>
		</article>
		<?php
	}

	echo '</div>';
	return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────────────────────
// Shared: Episode Card Renderer
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Render a single episode card (used by dashboard and grid).
 *
 * @param int  $ep_id    Episode post ID.
 * @param bool $mini     If true, renders a compact "continue listening" card.
 */
function microfix_render_episode_card( int $ep_id, bool $mini = false ): string {
	$user_id    = get_current_user_id();
	$can_access = microfix_user_can_access_episode( $ep_id );
	$locked     = ! $can_access;
	$type       = microfix_get_episode_content_type( $ep_id );
	$duration   = get_field( 'duration', $ep_id );
	$thumb      = microfix_get_thumbnail_url( $ep_id, 'medium' );
	$gradient   = microfix_get_card_gradient( $ep_id );
	$week_label = microfix_get_episode_week_label( $ep_id );
	$category   = microfix_get_episode_category( $ep_id );
	$reason     = microfix_get_access_denial_reason( $ep_id );
	$progress   = ( $user_id && ! $locked ) ? microfix_get_progress( $user_id, $ep_id ) : null;

	// Determine state label.
	$unlock_date = get_field( 'unlock_date', $ep_id );
	$unlock_ts   = $unlock_date ? strtotime( $unlock_date ) : 0;
	$is_future   = $unlock_ts && $unlock_ts > time();
	$is_coming_soon = $is_future && ! $locked; // accessible in future but not yet

	$state_class = '';
	if ( $is_future )   $state_class = 'mfx-ep-card--future';
	if ( $locked )      $state_class = 'mfx-ep-card--locked';

	ob_start();
	?>
	<article class="mfx-ep-card <?php echo esc_attr( $state_class ); ?><?php echo $mini ? ' mfx-ep-card--mini' : ''; ?>"
		data-episode-id="<?php echo esc_attr( $ep_id ); ?>">

		<div class="mfx-ep-card__thumb"
			style="<?php echo $thumb ? 'background-image:url(' . esc_url( $thumb ) . ')' : 'background:' . esc_attr( $gradient ); ?>">

			<?php if ( $locked ) : ?>
			<div class="mfx-ep-card__lock" aria-label="<?php esc_attr_e( 'Locked', 'microfix-audio-platform' ); ?>">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
			</div>
			<?php elseif ( ! $is_future ) : ?>
			<div class="mfx-ep-card__play-overlay">
				<?php echo do_shortcode( '[mfx_small_play_button episode_id="' . $ep_id . '"]' ); ?>
				<?php if ( $category ) : ?>
					<span class="mfx-cat-badge"><?php echo esc_html( $category->name ); ?></span>
				<?php endif; ?>
			</div>
			<?php endif; ?>

		</div>

		<div class="mfx-ep-card__body">
			<h4 class="mfx-ep-card__title"><?php echo esc_html( get_the_title( $ep_id ) ); ?></h4>

			<div class="mfx-ep-card__meta-top">
				<?php if ( $week_label ) : ?>
				<span class="mfx-week-label"><?php echo esc_html( $week_label ); ?></span>
				<?php endif; ?>

				<?php if ( $locked ) : ?>

					<div class="mfx-ep-card__locked-msg">
						-  <?php echo microfix_render_access_denied_message( $ep_id ); // phpcs:ignore ?>
					</div>

				<?php elseif ( $is_future ) : ?>

					<div class="mfx-ep-card__future-msg">
						<span>
							<?php echo esc_html( microfix_get_unlock_label( $ep_id ) ?: __( 'Coming Soon', 'microfix-audio-platform' ) ); ?>
						</span>
					</div>

				<?php else : ?>

					<?php if ( $unlock_date ) : ?>
						<span class="mfx-ep-card__date">
							- <?php echo esc_html( date_i18n( 'M j, Y', strtotime( $unlock_date ) ) ); ?>
						</span>
					<?php endif; ?>

				<?php endif; ?>
			</div>


			<div class="mfx-ep-card__meta-bottom">
				<?php if ( $duration ) : ?>
				<span class="mfx-ep-card__dur"><?php echo esc_html( $duration ); ?></span>
				<?php endif; ?>
			</div>

		</div>
	</article>
	<?php
	return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────────────────────
// Global Player Shell (rendered once per page)
// ─────────────────────────────────────────────────────────────────────────────

function microfix_render_global_player_shell(): string {
	static $rendered = false;
	if ( $rendered ) return '';
	$rendered = true;
	ob_start();
	include MICROFIX_PLUGIN_DIR . 'templates/player-bar.php';
	return ob_get_clean();
}

add_action( 'wp_footer', function () {
	echo microfix_render_global_player_shell(); // phpcs:ignore
} );
