<?php
/**
 * Sticky Bottom Player Bar
 * Rendered once in wp_footer. JS populates src + metadata dynamically.
 *
 * @package MicrofixAudioPlatform
 */
defined( 'ABSPATH' ) || exit;
?>
<div id="mfx-player-bar" class="mfx-player-bar mfx-player-bar--hidden"
	role="region" aria-label="<?php esc_attr_e( 'Media Player', 'microfix-audio-platform' ); ?>">

	<!-- Hidden audio element -->
	<audio id="mfx-audio-el" preload="metadata"></audio>

	<!-- Left: thumb + title -->
	<div class="mfx-player-bar__left">
		<div class="mfx-player-bar__thumb" id="mfx-bar-thumb" aria-hidden="true">
			<div class="mfx-player-bar__thumb-inner" id="mfx-bar-thumb-inner"></div>
		</div>
		<div class="mfx-player-bar__info">
			<span class="mfx-player-bar__title" id="mfx-bar-title"><?php esc_html_e( 'No episode playing', 'microfix-audio-platform' ); ?></span>
			<span class="mfx-player-bar__time-display">
				<span id="mfx-bar-current">0:00</span>
				<span class="mfx-player-bar__sep">/</span>
				<span id="mfx-bar-duration">0:00</span>
			</span>
		</div>
	</div>

	<!-- Center: controls + progress -->
	<div class="mfx-player-bar__center">
		<div class="mfx-player-bar__controls">
			<button class="mfx-pbar-btn" id="mfx-btn-rw"
				aria-label="<?php esc_attr_e( 'Rewind 15s', 'microfix-audio-platform' ); ?>" disabled>
				<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.5 3a9 9 0 1 0 7.14 14.37l-1.56-1.3A7 7 0 1 1 12.5 5V3z"/><path d="M12.5 3v6l-4-3 4-3z"/><text x="9" y="16" font-size="5.5" fill="currentColor">15</text></svg>
			</button>

			<button class="mfx-pbar-btn mfx-pbar-btn--play" id="mfx-btn-pp"
				aria-label="<?php esc_attr_e( 'Play', 'microfix-audio-platform' ); ?>" disabled>
				<svg class="mfx-icon-play" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="5,3 19,12 5,21"/></svg>
				<svg class="mfx-icon-pause" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" style="display:none"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
			</button>

			<button class="mfx-pbar-btn" id="mfx-btn-fw"
				aria-label="<?php esc_attr_e( 'Forward 15s', 'microfix-audio-platform' ); ?>" disabled>
				<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11.5 3a9 9 0 1 1-7.14 14.37l1.56-1.3A7 7 0 1 0 11.5 5V3z"/><path d="M11.5 3v6l4-3-4-3z"/><text x="9" y="16" font-size="5.5" fill="currentColor">15</text></svg>
			</button>
		</div>

		<!-- Progress bar -->
		<div class="mfx-player-bar__progress-row">
			<div class="mfx-pbar-progress"
				id="mfx-bar-progress"
				role="slider"
				aria-label="<?php esc_attr_e( 'Seek', 'microfix-audio-platform' ); ?>"
				aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"
				tabindex="0">
				<div class="mfx-pbar-progress__track">
					<div class="mfx-pbar-progress__fill" id="mfx-bar-fill"></div>
					<div class="mfx-pbar-progress__thumb" id="mfx-bar-thumb-dot"></div>
				</div>
			</div>
		</div>
	</div>

	<!-- Right: volume + speed + close -->
	<div class="mfx-player-bar__right">
		<select class="mfx-pbar-speed" id="mfx-bar-speed" aria-label="<?php esc_attr_e( 'Playback speed', 'microfix-audio-platform' ); ?>" disabled>
			<option value="0.75">0.75×</option>
			<option value="1" selected>1×</option>
			<option value="1.25">1.25×</option>
			<option value="1.5">1.5×</option>
			<option value="2">2×</option>
		</select>

		<button class="mfx-pbar-btn mfx-pbar-btn--close" id="mfx-btn-close"
			aria-label="<?php esc_attr_e( 'Close player', 'microfix-audio-platform' ); ?>">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
				<line x1="18" y1="6" x2="6" y2="18"/>
				<line x1="6" y1="6" x2="18" y2="18"/>
			</svg>
		</button>
	</div>

</div><!-- #mfx-player-bar -->

<!-- Video Modal -->
<div id="mfx-video-modal" class="mfx-video-modal" role="dialog" aria-modal="true"
	aria-label="<?php esc_attr_e( 'Video Player', 'microfix-audio-platform' ); ?>" hidden>
	<div class="mfx-video-modal__backdrop" id="mfx-video-backdrop"></div>
	<div class="mfx-video-modal__inner">
		<button class="mfx-video-modal__close" id="mfx-video-close"
			aria-label="<?php esc_attr_e( 'Close video', 'microfix-audio-platform' ); ?>">✕</button>
		<div class="mfx-video-modal__player">
			<video id="mfx-video-el" controls preload="metadata" style="width:100%;height:100%;display:block"></video>
			<iframe id="mfx-video-iframe" title="<?php esc_attr_e( 'Video', 'microfix-audio-platform' ); ?>"
				allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
				allowfullscreen style="width:100%;height:100%;border:none;display:none"></iframe>
		</div>
	</div>
</div>
