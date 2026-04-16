<?php
/**
 * Shortcodes
 *
 * All public-facing shortcodes for Elementor and page-builder integration.
 *
 * Available shortcodes:
 *  [play_button episode_id="123"]
 *  [featured_episode]
 *  [episodes_grid]
 *  [user_membership_status]
 *  [programs_grid]
 *
 * @package MicrofixAudioPlatform
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'microfix_register_shortcodes' );

function microfix_register_shortcodes(): void {
	add_shortcode( 'play_button',             'microfix_shortcode_play_button' );
	add_shortcode( 'featured_episode',        'microfix_shortcode_featured_episode' );
	add_shortcode( 'episodes_grid',           'microfix_shortcode_episodes_grid' );
	add_shortcode( 'user_membership_status',  'microfix_shortcode_membership_status' );
	add_shortcode( 'programs_grid',           'microfix_shortcode_programs_grid' );
}

// ─── [play_button] ────────────────────────────────────────────────────────────

/**
 * Render a play button or locked indicator for an episode.
 *
 * Attributes:
 *  - episode_id (int) — Episode post ID. Falls back to current post ID.
 *
 * @param array|string $atts Shortcode attributes.
 *
 * @return string HTML output.
 */
function microfix_shortcode_play_button( $atts ): string {
	$atts = shortcode_atts( [
		'episode_id' => get_the_ID(),
	], $atts, 'play_button' );

	$episode_id = absint( $atts['episode_id'] );

	if ( ! $episode_id || get_post_type( $episode_id ) !== 'episode' ) {
		return '';
	}

	ob_start();

	if ( ! microfix_user_can_access_episode( $episode_id ) ) {
		$denial_reason = microfix_get_access_denial_reason( $episode_id );
		$icon          = 'date_locked' === $denial_reason ? '📅' : '🔒';
		$label         = microfix_get_unlock_label( $episode_id );

		echo '<div class="mfx-play-button mfx-play-button--locked">';
		echo '<span class="mfx-lock-icon" aria-hidden="true">' . esc_html( $icon ) . '</span>';
		echo '<span class="mfx-lock-label">' . esc_html( $label ?: __( 'Locked', 'microfix-audio-platform' ) ) . '</span>';
		echo microfix_render_access_denied_message( $episode_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	} else {
		$content_type = microfix_get_episode_content_type( $episode_id );
		$title        = get_the_title( $episode_id );

		if ( 'video' === $content_type ) {
			$video_file_id = (int) get_field( 'video_file', $episode_id );

			if ( $video_file_id ) {
				// Self-hosted secure video.
				$stream_url = microfix_get_secure_video_url( $video_file_id, $episode_id );
				printf(
					'<button class="mfx-play-button mfx-play-button--video play-media"
						data-stream="%s"
						data-type="video"
						data-episode="%d"
						data-title="%s"
						aria-label="%s">
						<span class="mfx-play-icon" aria-hidden="true">▶</span>
						<span class="mfx-play-label">%s</span>
					</button>',
					esc_url( $stream_url ),
					$episode_id,
					esc_attr( $title ),
					esc_attr( sprintf( __( 'Play %s', 'microfix-audio-platform' ), $title ) ),
					esc_html__( 'Play Video', 'microfix-audio-platform' )
				);
			} else {
				// External video embed fallback.
				$video_url = get_field( 'video_url', $episode_id );
				if ( $video_url ) {
					printf(
						'<button class="mfx-play-button mfx-play-button--external-video play-media"
							data-stream="%s"
							data-type="external-video"
							data-episode="%d"
							data-title="%s"
							aria-label="%s">
							<span class="mfx-play-icon" aria-hidden="true">▶</span>
							<span class="mfx-play-label">%s</span>
						</button>',
						esc_url( $video_url ),
						$episode_id,
						esc_attr( $title ),
						esc_attr( sprintf( __( 'Play %s', 'microfix-audio-platform' ), $title ) ),
						esc_html__( 'Play Video', 'microfix-audio-platform' )
					);
				}
			}
		} else {
			// Audio episode.
			$audio_file_id = (int) get_field( 'audio_file', $episode_id );

			if ( ! $audio_file_id ) {
				echo '<div class="mfx-play-button mfx-play-button--unavailable">';
				echo esc_html__( 'Audio not available', 'microfix-audio-platform' );
				echo '</div>';
			} else {
				$stream_url = microfix_get_secure_audio_url( $audio_file_id, $episode_id );
				printf(
					'<button class="mfx-play-button mfx-play-button--audio play-media"
						data-stream="%s"
						data-type="audio"
						data-episode="%d"
						data-title="%s"
						aria-label="%s">
						<span class="mfx-play-icon" aria-hidden="true">▶</span>
						<span class="mfx-play-label">%s</span>
					</button>',
					esc_url( $stream_url ),
					$episode_id,
					esc_attr( $title ),
					esc_attr( sprintf( __( 'Play %s', 'microfix-audio-platform' ), $title ) ),
					esc_html__( 'Play Episode', 'microfix-audio-platform' )
				);
			}
		}
	}

	return ob_get_clean();
}

// ─── [featured_episode] ───────────────────────────────────────────────────────

/**
 * Render the featured episode card.
 *
 * @param array|string $atts Shortcode attributes (unused).
 *
 * @return string HTML output.
 */
function microfix_shortcode_featured_episode( $atts ): string {
	$atts = shortcode_atts( [], $atts, 'featured_episode' );

	$query = new WP_Query( [
		'post_type'      => 'episode',
		'posts_per_page' => 1,
		'meta_query'     => [
			[
				'key'     => 'is_featured',
				'value'   => '1',
				'compare' => '=',
			],
		],
	] );

	if ( ! $query->have_posts() ) {
		return '';
	}

	$episode    = $query->posts[0];
	$episode_id = $episode->ID;
	$thumb      = microfix_get_thumbnail_url( $episode_id, 'large' );
	$duration   = get_field( 'duration', $episode_id );
	$type       = microfix_get_episode_content_type( $episode_id );
	$can_access = microfix_user_can_access_episode( $episode_id );

	$program_id    = (int) get_field( 'program', $episode_id );
	$program_title = $program_id ? get_the_title( $program_id ) : '';

	ob_start();
	?>
	<div class="mfx-featured-episode mfx-featured-episode--<?php echo esc_attr( $type ); ?>">
		<?php if ( $thumb ) : ?>
		<div class="mfx-featured-episode__thumbnail">
			<img src="<?php echo esc_url( $thumb ); ?>"
				alt="<?php echo esc_attr( get_the_title( $episode_id ) ); ?>"
				loading="lazy">
			<div class="mfx-featured-episode__overlay">
				<?php echo do_shortcode( '[play_button episode_id="' . $episode_id . '"]' ); ?>
			</div>
		</div>
		<?php endif; ?>

		<div class="mfx-featured-episode__meta">
			<?php if ( $program_title ) : ?>
			<span class="mfx-featured-episode__program"><?php echo esc_html( $program_title ); ?></span>
			<?php endif; ?>

			<h2 class="mfx-featured-episode__title"><?php echo esc_html( get_the_title( $episode_id ) ); ?></h2>

			<div class="mfx-featured-episode__excerpt">
				<?php echo wp_kses_post( get_the_excerpt( $episode_id ) ); ?>
			</div>

			<div class="mfx-featured-episode__info">
				<?php if ( $duration ) : ?>
				<span class="mfx-badge mfx-badge--duration">
					<span aria-hidden="true"><?php echo ( 'video' === $type ) ? '🎬' : '🎧'; ?></span>
					<?php echo esc_html( $duration ); ?>
				</span>
				<?php endif; ?>

				<?php if ( ! $can_access ) : ?>
				<span class="mfx-badge mfx-badge--locked"><?php esc_html_e( 'Members Only', 'microfix-audio-platform' ); ?></span>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php

	wp_reset_postdata();
	return ob_get_clean();
}

// ─── [episodes_grid] ──────────────────────────────────────────────────────────

/**
 * Render all episodes grouped by their parent Program.
 *
 * Attributes:
 *  - program_id (int, optional) — Limit to a single program.
 *  - columns    (int)           — Grid columns, default 3.
 *
 * @param array|string $atts Shortcode attributes.
 *
 * @return string HTML output.
 */
function microfix_shortcode_episodes_grid( $atts ): string {
	$atts = shortcode_atts( [
		'program_id' => 0,
		'columns'    => 3,
	], $atts, 'episodes_grid' );

	$filter_program = absint( $atts['program_id'] );
	$columns        = max( 1, min( 6, absint( $atts['columns'] ) ) );

	// Fetch programs.
	if ( $filter_program ) {
		$programs = get_post( $filter_program ) ? [ get_post( $filter_program ) ] : [];
	} else {
		$programs = microfix_get_all_programs();
	}

	if ( empty( $programs ) ) {
		return '<p class="mfx-no-content">' . esc_html__( 'No programs found.', 'microfix-audio-platform' ) . '</p>';
	}

	ob_start();

	echo '<div class="mfx-episodes-grid-wrap">';

	foreach ( $programs as $program ) {
		$program_id = $program->ID;
		$episodes   = microfix_get_program_episodes( $program_id );

		if ( empty( $episodes ) ) {
			continue;
		}

		$program_thumb = microfix_get_thumbnail_url( $program_id, 'thumbnail' );
		?>
		<section class="mfx-program-section" id="program-<?php echo esc_attr( $program_id ); ?>">
			<div class="mfx-program-section__header">
				<?php if ( $program_thumb ) : ?>
				<img class="mfx-program-section__icon"
					src="<?php echo esc_url( $program_thumb ); ?>"
					alt=""
					aria-hidden="true">
				<?php endif; ?>
				<h3 class="mfx-program-section__title"><?php echo esc_html( get_the_title( $program_id ) ); ?></h3>
				<span class="mfx-program-section__count">
					<?php echo esc_html( sprintf( _n( '%d Episode', '%d Episodes', count( $episodes ), 'microfix-audio-platform' ), count( $episodes ) ) ); ?>
				</span>
			</div>

			<div class="mfx-episodes-grid mfx-episodes-grid--cols-<?php echo esc_attr( $columns ); ?>">
				<?php foreach ( $episodes as $episode ) :
					$ep_id      = $episode->ID;
					$can_access = microfix_user_can_access_episode( $ep_id );
					$type       = microfix_get_episode_content_type( $ep_id );
					$duration   = get_field( 'duration', $ep_id );
					$thumb      = microfix_get_thumbnail_url( $ep_id, 'medium' );
					$locked     = ! $can_access;
					$lock_label = microfix_get_unlock_label( $ep_id );
					$reason     = microfix_get_access_denial_reason( $ep_id );
					?>
				<article class="mfx-episode-card mfx-episode-card--<?php echo esc_attr( $type ); ?><?php echo $locked ? ' mfx-episode-card--locked' : ''; ?>"
					data-episode-id="<?php echo esc_attr( $ep_id ); ?>">

					<div class="mfx-episode-card__thumbnail">
						<?php if ( $thumb ) : ?>
						<img src="<?php echo esc_url( $thumb ); ?>"
							alt="<?php echo esc_attr( get_the_title( $ep_id ) ); ?>"
							loading="lazy">
						<?php endif; ?>

						<?php if ( $locked ) : ?>
						<div class="mfx-episode-card__lock-overlay" aria-hidden="true">
							<span class="mfx-lock-icon"><?php echo esc_html( 'date_locked' === $reason ? '📅' : '🔒' ); ?></span>
						</div>
						<?php else : ?>
						<div class="mfx-episode-card__play-overlay">
							<?php echo do_shortcode( '[play_button episode_id="' . $ep_id . '"]' ); ?>
						</div>
						<?php endif; ?>
					</div>

					<div class="mfx-episode-card__body">
						<h4 class="mfx-episode-card__title"><?php echo esc_html( get_the_title( $ep_id ) ); ?></h4>

						<div class="mfx-episode-card__meta">
							<span class="mfx-badge mfx-badge--type"><?php echo esc_html( ucfirst( $type ) ); ?></span>
							<?php if ( $duration ) : ?>
							<span class="mfx-badge mfx-badge--duration"><?php echo esc_html( $duration ); ?></span>
							<?php endif; ?>
						</div>

						<?php if ( $locked && $lock_label ) : ?>
						<p class="mfx-episode-card__unlock-date"><?php echo esc_html( $lock_label ); ?></p>
						<?php elseif ( $locked ) : ?>
						<p class="mfx-episode-card__unlock-date"><?php echo microfix_render_access_denied_message( $ep_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
						<?php endif; ?>
					</div>
				</article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	echo '</div><!-- .mfx-episodes-grid-wrap -->';

	return ob_get_clean();
}

// ─── [user_membership_status] ─────────────────────────────────────────────────

/**
 * Render the current user's membership status.
 *
 * @param array|string $atts Shortcode attributes (unused).
 *
 * @return string HTML output.
 */
function microfix_shortcode_membership_status( $atts ): string {
	$atts = shortcode_atts( [], $atts, 'user_membership_status' );

	ob_start();

	if ( ! is_user_logged_in() ) {
		$login_url = wp_login_url( get_permalink() );
		?>
		<div class="mfx-membership-status mfx-membership-status--guest">
			<span class="mfx-membership-status__icon" aria-hidden="true">👤</span>
			<div class="mfx-membership-status__text">
				<strong><?php esc_html_e( 'Not logged in', 'microfix-audio-platform' ); ?></strong>
				<a href="<?php echo esc_url( $login_url ); ?>" class="mfx-btn mfx-btn--primary">
					<?php esc_html_e( 'Log In', 'microfix-audio-platform' ); ?>
				</a>
			</div>
		</div>
		<?php
	} elseif ( microfix_has_active_membership() ) {
		$user        = wp_get_current_user();
		$memberships = [];

		// Try to fetch MemberPress membership names.
		if ( class_exists( 'MeprUser' ) ) {
			try {
				$mepr_user   = new MeprUser( $user->ID );
				$subscriptions = $mepr_user->active_product_subscriptions();
				foreach ( $subscriptions as $product ) {
					if ( is_object( $product ) && isset( $product->post_title ) ) {
						$memberships[] = $product->post_title;
					} elseif ( is_numeric( $product ) ) {
						$memberships[] = get_the_title( (int) $product );
					}
				}
			} catch ( \Throwable $e ) {
				// Silently ignore.
			}
		}

		?>
		<div class="mfx-membership-status mfx-membership-status--active">
			<span class="mfx-membership-status__icon" aria-hidden="true">✅</span>
			<div class="mfx-membership-status__text">
				<strong><?php echo esc_html( sprintf( __( 'Welcome back, %s!', 'microfix-audio-platform' ), $user->display_name ) ); ?></strong>
				<span class="mfx-membership-status__label"><?php esc_html_e( 'Active Member', 'microfix-audio-platform' ); ?></span>
				<?php if ( ! empty( $memberships ) ) : ?>
				<ul class="mfx-membership-status__list">
					<?php foreach ( $memberships as $name ) : ?>
					<li><?php echo esc_html( $name ); ?></li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>
			</div>
		</div>
		<?php
	} else {
		$membership_url = apply_filters( 'microfix_membership_page_url', home_url( '/membership/' ), 0 );
		?>
		<div class="mfx-membership-status mfx-membership-status--inactive">
			<span class="mfx-membership-status__icon" aria-hidden="true">⚠️</span>
			<div class="mfx-membership-status__text">
				<strong><?php esc_html_e( 'No active membership', 'microfix-audio-platform' ); ?></strong>
				<a href="<?php echo esc_url( $membership_url ); ?>" class="mfx-btn mfx-btn--primary">
					<?php esc_html_e( 'Get Access', 'microfix-audio-platform' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	return ob_get_clean();
}

// ─── [programs_grid] ──────────────────────────────────────────────────────────

/**
 * Render a grid of all programs (course catalog).
 *
 * Attributes:
 *  - columns (int) — Grid columns, default 3.
 *
 * @param array|string $atts Shortcode attributes.
 *
 * @return string HTML output.
 */
function microfix_shortcode_programs_grid( $atts ): string {
	$atts = shortcode_atts( [
		'columns' => 3,
	], $atts, 'programs_grid' );

	$columns  = max( 1, min( 6, absint( $atts['columns'] ) ) );
	$programs = microfix_get_all_programs();

	if ( empty( $programs ) ) {
		return '<p class="mfx-no-content">' . esc_html__( 'No programs available.', 'microfix-audio-platform' ) . '</p>';
	}

	ob_start();
	echo '<div class="mfx-programs-grid mfx-programs-grid--cols-' . esc_attr( $columns ) . '">';

	foreach ( $programs as $program ) {
		$program_id    = $program->ID;
		$thumb         = microfix_get_thumbnail_url( $program_id, 'medium' );
		$episode_count = count( microfix_get_program_episodes( $program_id ) );
		$description   = get_field( 'program_description', $program_id ) ?: get_the_excerpt( $program_id );
		?>
		<article class="mfx-program-card" id="program-card-<?php echo esc_attr( $program_id ); ?>">
			<?php if ( $thumb ) : ?>
			<div class="mfx-program-card__thumbnail">
				<img src="<?php echo esc_url( $thumb ); ?>"
					alt="<?php echo esc_attr( get_the_title( $program_id ) ); ?>"
					loading="lazy">
			</div>
			<?php endif; ?>

			<div class="mfx-program-card__body">
				<h3 class="mfx-program-card__title"><?php echo esc_html( get_the_title( $program_id ) ); ?></h3>

				<?php if ( $description ) : ?>
				<p class="mfx-program-card__description"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>

				<div class="mfx-program-card__footer">
					<span class="mfx-badge mfx-badge--count">
						<?php echo esc_html( sprintf( _n( '%d Episode', '%d Episodes', $episode_count, 'microfix-audio-platform' ), $episode_count ) ); ?>
					</span>
					<a href="<?php echo esc_url( get_permalink( $program_id ) ); ?>" class="mfx-btn mfx-btn--secondary">
						<?php esc_html_e( 'View Program →', 'microfix-audio-platform' ); ?>
					</a>
				</div>
			</div>
		</article>
		<?php
	}

	echo '</div><!-- .mfx-programs-grid -->';

	// Append the sticky media player shell (rendered once per page).
	echo microfix_render_global_player_shell(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	return ob_get_clean();
}

// ─── Global Player Shell ──────────────────────────────────────────────────────

/**
 * Output the sticky bottom player HTML shell.
 * JS fills in the src and metadata dynamically.
 *
 * Only rendered once per page (tracked via static flag).
 *
 * @return string HTML or empty string.
 */
function microfix_render_global_player_shell(): string {
	static $rendered = false;

	if ( $rendered ) {
		return '';
	}
	$rendered = true;

	ob_start();
	include MICROFIX_PLUGIN_DIR . 'templates/dashboard.php';
	return ob_get_clean();
}

// Also output the player shell once per page via wp_footer.
add_action( 'wp_footer', function () {
	echo microfix_render_global_player_shell(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
} );
