<?php
/**
 * Global Media Player Shell
 *
 * Rendered once per page in wp_footer. JavaScript populates
 * the <audio>/<video> src and metadata dynamically when a user
 * clicks a play button.
 *
 * @package MicrofixAudioPlatform
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="mfx-global-player" class="mfx-global-player mfx-global-player--hidden" role="region" aria-label="<?php esc_attr_e( 'Media Player', 'microfix-audio-platform' ); ?>">

	<!-- Thumbnail -->
	<div class="mfx-player__thumbnail" id="mfx-player-thumbnail" aria-hidden="true">
		<div class="mfx-player__thumbnail-inner"></div>
	</div>

	<!-- Info -->
	<div class="mfx-player__info">
		<span class="mfx-player__title" id="mfx-player-title"><?php esc_html_e( 'No episode selected', 'microfix-audio-platform' ); ?></span>
		<span class="mfx-player__type-badge" id="mfx-player-type"></span>
	</div>

	<!-- Audio Element (used for audio episodes) -->
	<audio id="mfx-audio-element"
		preload="metadata"
		aria-label="<?php esc_attr_e( 'Audio player', 'microfix-audio-platform' ); ?>">
		<p><?php esc_html_e( 'Your browser does not support the audio element.', 'microfix-audio-platform' ); ?></p>
	</audio>

	<!-- Controls -->
	<div class="mfx-player__controls">

		<button class="mfx-player__btn mfx-player__btn--rewind"
			id="mfx-btn-rewind"
			aria-label="<?php esc_attr_e( 'Rewind 15 seconds', 'microfix-audio-platform' ); ?>"
			disabled>
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<path d="M12 5V1L7 6l5 5V7a7 7 0 110 14 7 7 0 01-6.32-10"/>
				<text x="7.5" y="16" font-size="5" fill="currentColor" stroke="none">15</text>
			</svg>
		</button>

		<button class="mfx-player__btn mfx-player__btn--play-pause"
			id="mfx-btn-play-pause"
			aria-label="<?php esc_attr_e( 'Play', 'microfix-audio-platform' ); ?>"
			disabled>
			<svg class="mfx-icon-play" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
				<polygon points="5,3 19,12 5,21"/>
			</svg>
			<svg class="mfx-icon-pause" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" style="display:none">
				<rect x="6" y="4" width="4" height="16"/>
				<rect x="14" y="4" width="4" height="16"/>
			</svg>
		</button>

		<button class="mfx-player__btn mfx-player__btn--forward"
			id="mfx-btn-forward"
			aria-label="<?php esc_attr_e( 'Forward 15 seconds', 'microfix-audio-platform' ); ?>"
			disabled>
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<path d="M12 5V1l5 5-5 5V7a7 7 0 100 14 7 7 0 006.32-10"/>
				<text x="7.5" y="16" font-size="5" fill="currentColor" stroke="none">15</text>
			</svg>
		</button>

	</div>

	<!-- Progress & Time -->
	<div class="mfx-player__progress-wrap">
		<span class="mfx-player__time mfx-player__time--current" id="mfx-time-current">0:00</span>

		<div class="mfx-player__progress-bar"
			role="slider"
			id="mfx-progress-bar"
			aria-label="<?php esc_attr_e( 'Seek', 'microfix-audio-platform' ); ?>"
			aria-valuemin="0"
			aria-valuemax="100"
			aria-valuenow="0"
			tabindex="0">
			<div class="mfx-player__progress-track">
				<div class="mfx-player__progress-fill" id="mfx-progress-fill"></div>
				<div class="mfx-player__progress-thumb" id="mfx-progress-thumb"></div>
			</div>
		</div>

		<span class="mfx-player__time mfx-player__time--duration" id="mfx-time-duration">0:00</span>
	</div>

	<!-- Volume & Speed -->
	<div class="mfx-player__secondary-controls">

		<div class="mfx-player__volume-wrap">
			<button class="mfx-player__btn mfx-player__btn--mute"
				id="mfx-btn-mute"
				aria-label="<?php esc_attr_e( 'Mute', 'microfix-audio-platform' ); ?>"
				disabled>
				<svg class="mfx-icon-volume" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
					<polygon points="11,5 6,9 2,9 2,15 6,15 11,19"/>
					<path d="M15.54 8.46a5 5 0 010 7.07M19.07 4.93a10 10 0 010 14.14"/>
				</svg>
				<svg class="mfx-icon-muted" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" style="display:none">
					<polygon points="11,5 6,9 2,9 2,15 6,15 11,19"/>
					<line x1="23" y1="9" x2="17" y2="15" stroke="currentColor" stroke-width="2"/>
					<line x1="17" y1="9" x2="23" y2="15" stroke="currentColor" stroke-width="2"/>
				</svg>
			</button>
			<input type="range"
				id="mfx-volume-slider"
				class="mfx-player__volume-slider"
				min="0" max="1" step="0.05" value="1"
				aria-label="<?php esc_attr_e( 'Volume', 'microfix-audio-platform' ); ?>"
				disabled>
		</div>

		<div class="mfx-player__speed-wrap">
			<label for="mfx-speed-select" class="screen-reader-text"><?php esc_html_e( 'Playback speed', 'microfix-audio-platform' ); ?></label>
			<select id="mfx-speed-select" class="mfx-player__speed-select" disabled aria-label="<?php esc_attr_e( 'Playback speed', 'microfix-audio-platform' ); ?>">
				<option value="0.75">0.75×</option>
				<option value="1" selected>1×</option>
				<option value="1.25">1.25×</option>
				<option value="1.5">1.5×</option>
				<option value="2">2×</option>
			</select>
		</div>

		<button class="mfx-player__btn mfx-player__btn--close"
			id="mfx-btn-close"
			aria-label="<?php esc_attr_e( 'Close player', 'microfix-audio-platform' ); ?>">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<line x1="18" y1="6" x2="6" y2="18"/>
				<line x1="6" y1="6" x2="18" y2="18"/>
			</svg>
		</button>

	</div>

	<!-- Video Modal Overlay (for video episodes) -->
	<div id="mfx-video-modal" class="mfx-video-modal" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Video Player', 'microfix-audio-platform' ); ?>" hidden>
		<div class="mfx-video-modal__backdrop" id="mfx-video-backdrop"></div>
		<div class="mfx-video-modal__inner">
			<button class="mfx-video-modal__close" id="mfx-video-close" aria-label="<?php esc_attr_e( 'Close video', 'microfix-audio-platform' ); ?>">✕</button>
			<div class="mfx-video-modal__player" id="mfx-video-player-wrap">
				<video id="mfx-video-element"
					controls
					preload="metadata"
					aria-label="<?php esc_attr_e( 'Video player', 'microfix-audio-platform' ); ?>">
					<p><?php esc_html_e( 'Your browser does not support the video element.', 'microfix-audio-platform' ); ?></p>
				</video>
				<iframe id="mfx-video-iframe"
					title="<?php esc_attr_e( 'External video player', 'microfix-audio-platform' ); ?>"
					allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
					allowfullscreen
					style="display:none">
				</iframe>
			</div>
		</div>
	</div>

</div><!-- #mfx-global-player -->
