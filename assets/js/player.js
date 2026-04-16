/**
 * Microfix Audio Platform — Media Player
 *
 * Responsibilities:
 *  - Intercept .play-media button clicks
 *  - Route to audio (sticky bar) or video (modal overlay) player
 *  - Full playback controls: play/pause, seek, volume, speed, ±15s
 *  - Single shared player instance — switching episode stops previous
 *  - Keyboard accessible (Space, ←, →, M)
 *
 * @package MicrofixAudioPlatform
 */

( function () {
	'use strict';

	// ─── State ──────────────────────────────────────────────────────────────────

	const state = {
		currentEpisodeId : null,
		currentType      : null,   // 'audio' | 'video' | 'external-video'
		isPlaying        : false,
		isMuted          : false,
		isDragging       : false,
	};

	// ─── Element references ──────────────────────────────────────────────────────

	const el = {
		player         : document.getElementById( 'mfx-global-player' ),
		audio          : document.getElementById( 'mfx-audio-element' ),
		title          : document.getElementById( 'mfx-player-title' ),
		typeBadge      : document.getElementById( 'mfx-player-type' ),
		thumbnail      : document.querySelector( '.mfx-player__thumbnail-inner' ),

		btnPlayPause   : document.getElementById( 'mfx-btn-play-pause' ),
		iconPlay       : document.querySelector( '.mfx-icon-play' ),
		iconPause      : document.querySelector( '.mfx-icon-pause' ),

		btnRewind      : document.getElementById( 'mfx-btn-rewind' ),
		btnForward     : document.getElementById( 'mfx-btn-forward' ),
		btnMute        : document.getElementById( 'mfx-btn-mute' ),
		iconVolume     : document.querySelector( '.mfx-icon-volume' ),
		iconMuted      : document.querySelector( '.mfx-icon-muted' ),

		progressBar    : document.getElementById( 'mfx-progress-bar' ),
		progressFill   : document.getElementById( 'mfx-progress-fill' ),
		progressThumb  : document.getElementById( 'mfx-progress-thumb' ),
		timeCurrent    : document.getElementById( 'mfx-time-current' ),
		timeDuration   : document.getElementById( 'mfx-time-duration' ),

		volumeSlider   : document.getElementById( 'mfx-volume-slider' ),
		speedSelect    : document.getElementById( 'mfx-speed-select' ),
		btnClose       : document.getElementById( 'mfx-btn-close' ),

		// Video modal.
		videoModal     : document.getElementById( 'mfx-video-modal' ),
		videoElement   : document.getElementById( 'mfx-video-element' ),
		videoIframe    : document.getElementById( 'mfx-video-iframe' ),
		videoBackdrop  : document.getElementById( 'mfx-video-backdrop' ),
		btnVideoClose  : document.getElementById( 'mfx-video-close' ),
	};

	// Abort silently if player shell not present in DOM.
	if ( ! el.player || ! el.audio ) return;

	// ─── Utility helpers ─────────────────────────────────────────────────────────

	/**
	 * Format seconds to m:ss or h:mm:ss.
	 *
	 * @param {number} seconds
	 * @returns {string}
	 */
	function formatTime( seconds ) {
		if ( isNaN( seconds ) || ! isFinite( seconds ) ) return '0:00';
		const h = Math.floor( seconds / 3600 );
		const m = Math.floor( ( seconds % 3600 ) / 60 );
		const s = Math.floor( seconds % 60 );
		const ss = String( s ).padStart( 2, '0' );
		if ( h > 0 ) {
			return `${ h }:${ String( m ).padStart( 2, '0' ) }:${ ss }`;
		}
		return `${ m }:${ ss }`;
	}

	/**
	 * Clamp a number between min and max.
	 *
	 * @param {number} val
	 * @param {number} min
	 * @param {number} max
	 * @returns {number}
	 */
	function clamp( val, min, max ) {
		return Math.min( Math.max( val, min ), max );
	}

	/**
	 * Calculate seek position from a pointer/click event on the progress bar.
	 *
	 * @param {PointerEvent|MouseEvent} event
	 * @returns {number} 0–1 ratio
	 */
	function getSeekRatio( event ) {
		const rect  = el.progressBar.querySelector( '.mfx-player__progress-track' ).getBoundingClientRect();
		const ratio = ( event.clientX - rect.left ) / rect.width;
		return clamp( ratio, 0, 1 );
	}

	// ─── Player UI updates ───────────────────────────────────────────────────────

	function enableControls() {
		[ el.btnPlayPause, el.btnRewind, el.btnForward,
		  el.btnMute, el.volumeSlider, el.speedSelect ].forEach( el => el.disabled = false );
	}

	function updatePlayPauseUI( playing ) {
		state.isPlaying = playing;
		el.iconPlay.style.display  = playing ? 'none' : '';
		el.iconPause.style.display = playing ? '' : 'none';
		el.btnPlayPause.setAttribute( 'aria-label', playing ? 'Pause' : 'Play' );
	}

	function updateProgress() {
		const audio    = el.audio;
		const duration = audio.duration || 0;
		const current  = audio.currentTime || 0;
		const pct      = duration ? ( current / duration ) * 100 : 0;

		el.progressFill.style.width       = pct + '%';
		el.progressThumb.style.left       = pct + '%';
		el.timeCurrent.textContent        = formatTime( current );
		el.timeDuration.textContent       = formatTime( duration );
		el.progressBar.setAttribute( 'aria-valuenow', Math.round( pct ) );
	}

	function showPlayer() {
		el.player.classList.remove( 'mfx-global-player--hidden' );
		el.player.classList.add( 'mfx-global-player--visible' );
	}

	function hidePlayer() {
		el.player.classList.add( 'mfx-global-player--hidden' );
		el.player.classList.remove( 'mfx-global-player--visible' );
	}

	function setEpisodeMeta( title, type ) {
		el.title.textContent     = title || '';
		el.typeBadge.textContent = type === 'audio' ? '🎧 Audio' : '🎬 Video';
	}

	function resetAudio() {
		el.audio.pause();
		el.audio.src     = '';
		el.audio.currentTime = 0;
		updatePlayPauseUI( false );
		updateProgress();
	}

	// ─── Audio episode loader ────────────────────────────────────────────────────

	/**
	 * Load and play an audio episode in the sticky bottom bar.
	 *
	 * @param {string} streamUrl  Secure stream endpoint URL.
	 * @param {string} title      Episode title.
	 * @param {number} episodeId  Episode post ID.
	 */
	function loadAudio( streamUrl, title, episodeId ) {
		// Same episode already playing → just toggle play/pause.
		if ( state.currentEpisodeId === episodeId && state.currentType === 'audio' ) {
			togglePlayPause();
			return;
		}

		state.currentEpisodeId = episodeId;
		state.currentType      = 'audio';

		resetAudio();
		el.audio.src = streamUrl;
		el.audio.load();

		setEpisodeMeta( title, 'audio' );
		showPlayer();
		enableControls();

		el.audio.play().catch( err => {
			console.error( '[Microfix Player] Audio play error:', err );
		} );
	}

	// ─── Video episode loader ────────────────────────────────────────────────────

	/**
	 * Open the video modal and load a self-hosted video.
	 *
	 * @param {string} streamUrl  Secure stream endpoint URL.
	 * @param {string} title      Episode title.
	 * @param {number} episodeId  Episode post ID.
	 */
	function loadVideo( streamUrl, title, episodeId ) {
		// Stop any audio playing.
		resetAudio();
		hidePlayer();

		state.currentEpisodeId = episodeId;
		state.currentType      = 'video';

		el.videoIframe.style.display  = 'none';
		el.videoElement.style.display = '';
		el.videoIframe.src            = '';

		el.videoElement.src = streamUrl;
		el.videoElement.load();

		openVideoModal( title );
		el.videoElement.play().catch( err => {
			console.error( '[Microfix Player] Video play error:', err );
		} );
	}

	/**
	 * Open the video modal and embed an external video (YouTube / Vimeo).
	 *
	 * Converts watch URLs to embeddable URLs automatically.
	 *
	 * @param {string} externalUrl  External video URL.
	 * @param {string} title        Episode title.
	 * @param {number} episodeId    Episode post ID.
	 */
	function loadExternalVideo( externalUrl, title, episodeId ) {
		resetAudio();
		hidePlayer();

		state.currentEpisodeId = episodeId;
		state.currentType      = 'external-video';

		el.videoElement.style.display = 'none';
		el.videoElement.src           = '';
		el.videoIframe.style.display  = '';

		const embedUrl = resolveEmbedUrl( externalUrl );
		el.videoIframe.src = embedUrl;

		openVideoModal( title );
	}

	/**
	 * Convert a YouTube / Vimeo watch URL to an embed URL.
	 *
	 * @param {string} url
	 * @returns {string}
	 */
	function resolveEmbedUrl( url ) {
		// YouTube — watch?v=ID or youtu.be/ID
		const ytMatch = url.match( /(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]{11})/ );
		if ( ytMatch ) {
			return `https://www.youtube.com/embed/${ ytMatch[ 1 ] }?autoplay=1&rel=0`;
		}

		// Vimeo — vimeo.com/ID
		const vimeoMatch = url.match( /vimeo\.com\/(\d+)/ );
		if ( vimeoMatch ) {
			return `https://player.vimeo.com/video/${ vimeoMatch[ 1 ] }?autoplay=1`;
		}

		// Unknown: return as-is and hope the browser handles it.
		return url;
	}

	// ─── Video modal ─────────────────────────────────────────────────────────────

	function openVideoModal( title ) {
		el.videoModal.hidden = false;
		el.videoModal.setAttribute( 'aria-label', title || 'Video Player' );
		document.body.classList.add( 'mfx-modal-open' );

		// Trap focus inside modal.
		setTimeout( () => el.btnVideoClose.focus(), 50 );
	}

	function closeVideoModal() {
		el.videoModal.hidden = true;
		document.body.classList.remove( 'mfx-modal-open' );

		el.videoElement.pause();
		el.videoElement.src = '';
		el.videoIframe.src  = '';

		state.currentEpisodeId = null;
		state.currentType      = null;
	}

	// ─── Playback controls ───────────────────────────────────────────────────────

	function togglePlayPause() {
		if ( ! el.audio.src ) return;
		if ( el.audio.paused ) {
			el.audio.play().catch( console.error );
		} else {
			el.audio.pause();
		}
	}

	function skip( seconds ) {
		if ( ! el.audio.src ) return;
		el.audio.currentTime = clamp( el.audio.currentTime + seconds, 0, el.audio.duration || 0 );
	}

	function seek( ratio ) {
		if ( ! el.audio.duration ) return;
		el.audio.currentTime = ratio * el.audio.duration;
	}

	function setVolume( level ) {
		el.audio.volume       = clamp( level, 0, 1 );
		el.volumeSlider.value = el.audio.volume;
	}

	function toggleMute() {
		el.audio.muted        = ! el.audio.muted;
		state.isMuted         = el.audio.muted;
		el.iconVolume.style.display = el.audio.muted ? 'none' : '';
		el.iconMuted.style.display  = el.audio.muted ? '' : 'none';
		el.btnMute.setAttribute( 'aria-label', el.audio.muted ? 'Unmute' : 'Mute' );
	}

	// ─── Event: play button clicks ────────────────────────────────────────────────

	document.addEventListener( 'click', function ( event ) {
		const btn = event.target.closest( '.play-media' );
		if ( ! btn ) return;

		event.preventDefault();

		const streamUrl  = btn.dataset.stream;
		const type       = btn.dataset.type;   // 'audio' | 'video' | 'external-video'
		const episodeId  = parseInt( btn.dataset.episode, 10 ) || 0;
		const title      = btn.dataset.title || 'Episode';

		if ( ! streamUrl ) {
			console.warn( '[Microfix Player] No stream URL on button', btn );
			return;
		}

		switch ( type ) {
			case 'audio':
				loadAudio( streamUrl, title, episodeId );
				break;
			case 'video':
				loadVideo( streamUrl, title, episodeId );
				break;
			case 'external-video':
				loadExternalVideo( streamUrl, title, episodeId );
				break;
			default:
				console.warn( '[Microfix Player] Unknown content type:', type );
		}
	} );

	// ─── Audio element events ────────────────────────────────────────────────────

	el.audio.addEventListener( 'play',       () => updatePlayPauseUI( true ) );
	el.audio.addEventListener( 'pause',      () => updatePlayPauseUI( false ) );
	el.audio.addEventListener( 'ended',      () => updatePlayPauseUI( false ) );
	el.audio.addEventListener( 'timeupdate', updateProgress );
	el.audio.addEventListener( 'loadedmetadata', updateProgress );
	el.audio.addEventListener( 'error', function () {
		console.error( '[Microfix Player] Audio error', el.audio.error );
		updatePlayPauseUI( false );
	} );

	// ─── Player control events ───────────────────────────────────────────────────

	el.btnPlayPause.addEventListener( 'click', togglePlayPause );
	el.btnRewind.addEventListener(    'click', () => skip( -15 ) );
	el.btnForward.addEventListener(   'click', () => skip( 15 ) );
	el.btnMute.addEventListener(      'click', toggleMute );

	el.volumeSlider.addEventListener( 'input', () => setVolume( parseFloat( el.volumeSlider.value ) ) );

	el.speedSelect.addEventListener( 'change', () => {
		el.audio.playbackRate = parseFloat( el.speedSelect.value ) || 1;
	} );

	el.btnClose.addEventListener( 'click', () => {
		resetAudio();
		hidePlayer();
		state.currentEpisodeId = null;
		state.currentType      = null;
	} );

	// ─── Progress bar seek ───────────────────────────────────────────────────────

	el.progressBar.addEventListener( 'pointerdown', function ( e ) {
		state.isDragging = true;
		seek( getSeekRatio( e ) );
		el.progressBar.setPointerCapture( e.pointerId );
	} );

	el.progressBar.addEventListener( 'pointermove', function ( e ) {
		if ( ! state.isDragging ) return;
		seek( getSeekRatio( e ) );
	} );

	el.progressBar.addEventListener( 'pointerup', function () {
		state.isDragging = false;
	} );

	el.progressBar.addEventListener( 'keydown', function ( e ) {
		const STEP = 5; // seconds
		if ( e.key === 'ArrowRight' ) { skip(  STEP ); e.preventDefault(); }
		if ( e.key === 'ArrowLeft'  ) { skip( -STEP ); e.preventDefault(); }
	} );

	// ─── Video modal events ──────────────────────────────────────────────────────

	el.btnVideoClose.addEventListener(  'click',   closeVideoModal );
	el.videoBackdrop.addEventListener(  'click',   closeVideoModal );

	// ─── Global keyboard shortcuts ───────────────────────────────────────────────

	document.addEventListener( 'keydown', function ( e ) {
		// Ignore when focus is on an input, textarea, or select.
		const tag = document.activeElement?.tagName;
		if ( [ 'INPUT', 'TEXTAREA', 'SELECT' ].includes( tag ) ) return;

		// Only when audio player is active.
		if ( state.currentType !== 'audio' ) return;

		switch ( e.code ) {
			case 'Space':
				e.preventDefault();
				togglePlayPause();
				break;
			case 'ArrowRight':
				skip( 15 );
				break;
			case 'ArrowLeft':
				skip( -15 );
				break;
			case 'KeyM':
				toggleMute();
				break;
		}
	} );

} )();
