/**
 * Microfix Audio Platform — Player JS v2
 *
 * Handles:
 *  - Audio: sticky bottom bar with full controls
 *  - Video: modal overlay (self-hosted + YouTube/Vimeo)
 *  - Progress saving via AJAX (debounced, every 5s)
 *  - Resume playback from saved position
 *  - Keyboard shortcuts: Space, ←, →, M
 *
 * @package MicrofixAudioPlatform
 */

( function () {
	'use strict';

	// ── Config ────────────────────────────────────────────────────────────────
	const cfg = window.MicrofixConfig || {};

	// ── State ─────────────────────────────────────────────────────────────────
	const state = {
		episodeId  : null,
		type       : null,      // 'audio' | 'video' | 'external-video'
		isDragging : false,
		saveTimer  : null,
	};

	// ── DOM References ────────────────────────────────────────────────────────
	const bar       = document.getElementById( 'mfx-player-bar' );
	const audioEl   = document.getElementById( 'mfx-audio-el' );

	// bail if player shell not present
	if ( ! bar || ! audioEl ) return;

	const barThumbInner = document.getElementById( 'mfx-bar-thumb-inner' );
	const barTitle      = document.getElementById( 'mfx-bar-title' );
	const barCurrent    = document.getElementById( 'mfx-bar-current' );
	const barDuration   = document.getElementById( 'mfx-bar-duration' );

	const btnPP    = document.getElementById( 'mfx-btn-pp' );
	const btnRW    = document.getElementById( 'mfx-btn-rw' );
	const btnFW    = document.getElementById( 'mfx-btn-fw' );
	const btnClose = document.getElementById( 'mfx-btn-close' );
	const speed    = document.getElementById( 'mfx-bar-speed' );

	const iconPlay  = bar.querySelector( '.mfx-icon-play' );
	const iconPause = bar.querySelector( '.mfx-icon-pause' );

	const progress      = document.getElementById( 'mfx-bar-progress' );
	const progressFill  = document.getElementById( 'mfx-bar-fill' );
	const progressThumb = document.getElementById( 'mfx-bar-thumb-dot' );

	// Video modal
	const videoModal   = document.getElementById( 'mfx-video-modal' );
	const videoEl      = document.getElementById( 'mfx-video-el' );
	const videoIframe  = document.getElementById( 'mfx-video-iframe' );
	const videoBackdrop = document.getElementById( 'mfx-video-backdrop' );
	const videoClose   = document.getElementById( 'mfx-video-close' );

	// ── Utils ─────────────────────────────────────────────────────────────────

	function fmt( s ) {
		if ( ! isFinite( s ) || isNaN( s ) ) return '0:00';
		const h  = Math.floor( s / 3600 );
		const m  = Math.floor( ( s % 3600 ) / 60 );
		const ss = String( Math.floor( s % 60 ) ).padStart( 2, '0' );
		return h > 0 ? `${ h }:${ String( m ).padStart( 2, '0' ) }:${ ss }` : `${ m }:${ ss }`;
	}

	function clamp( v, lo, hi ) { return Math.min( Math.max( v, lo ), hi ); }

	function resolveEmbedUrl( url ) {
		const yt = url.match( /(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]{11})/ );
		if ( yt ) return `https://www.youtube.com/embed/${ yt[1] }?autoplay=1&rel=0`;
		const vm = url.match( /vimeo\.com\/(\d+)/ );
		if ( vm ) return `https://player.vimeo.com/video/${ vm[1] }?autoplay=1`;
		return url;
	}

	// ── UI ────────────────────────────────────────────────────────────────────

	function showBar()  { bar.classList.add( 'mfx-player-bar--visible' ); bar.classList.remove( 'mfx-player-bar--hidden' ); }
	function hideBar()  { bar.classList.add( 'mfx-player-bar--hidden' );  bar.classList.remove( 'mfx-player-bar--visible' ); }

	function enableControls() {
		[ btnPP, btnRW, btnFW, speed ].forEach( el => el.disabled = false );
	}

	function setPlaying( playing ) {
		iconPlay.style.display  = playing ? 'none' : '';
		iconPause.style.display = playing ? '' : 'none';
		btnPP.setAttribute( 'aria-label', playing ? 'Pause' : 'Play' );
	}

	function updateProgress() {
		const dur = audioEl.duration || 0;
		const cur = audioEl.currentTime || 0;
		const pct = dur ? ( cur / dur ) * 100 : 0;

		progressFill.style.width  = pct + '%';
		progressThumb.style.left  = pct + '%';
		barCurrent.textContent    = fmt( cur );
		barDuration.textContent   = fmt( dur );
		progress.setAttribute( 'aria-valuenow', Math.round( pct ) );
	}

	function setMeta( title, thumbUrl ) {
		barTitle.textContent = title || '';
		if ( thumbUrl ) {
			barThumbInner.style.backgroundImage = `url(${ thumbUrl })`;
		} else {
			barThumbInner.style.backgroundImage = '';
		}
	}

	// ── Audio ─────────────────────────────────────────────────────────────────

	function loadAudio( url, title, episodeId, resumeAt ) {
		// Same episode? Just toggle.
		if ( state.episodeId === episodeId && state.type === 'audio' ) {
			togglePP();
			return;
		}

		state.episodeId = episodeId;
		state.type      = 'audio';

		audioEl.pause();
		audioEl.src         = url;
		audioEl.currentTime = 0;
		audioEl.load();

		// Fetch thumbnail for bar via WP REST if possible — fallback to empty.
		fetchEpisodeMeta( episodeId, title );

		showBar();
		enableControls();

		audioEl.addEventListener( 'loadedmetadata', function onMeta() {
			if ( resumeAt > 0 ) audioEl.currentTime = resumeAt;
			audioEl.play().catch( console.error );
			audioEl.removeEventListener( 'loadedmetadata', onMeta );
		}, { once: true } );
	}

	function fetchEpisodeMeta( episodeId, fallbackTitle ) {
		if ( ! cfg.siteUrl ) { setMeta( fallbackTitle, '' ); return; }

		fetch( `${ cfg.siteUrl }/wp-json/wp/v2/episode/${ episodeId }?_fields=title,mfx_thumbnail_url,mfx_duration` )
			.then( r => r.ok ? r.json() : null )
			.then( data => {
				if ( ! data ) { setMeta( fallbackTitle, '' ); return; }

				// Decode HTML entities in title.
				const parser = new DOMParser();
				const title  = data.title?.rendered
					? parser.parseFromString( data.title.rendered, 'text/html' ).body.textContent
					: fallbackTitle;

				setMeta( title, data.mfx_thumbnail_url || '' );

				// Show duration in bar if available.
				if ( data.mfx_duration && barDuration ) {
					barDuration.textContent = data.mfx_duration;
				}
			} )
			.catch( () => setMeta( fallbackTitle, '' ) );
	}

	// ── Video ─────────────────────────────────────────────────────────────────

	function loadVideo( url, title, episodeId ) {
		audioEl.pause();
		hideBar();

		state.episodeId = episodeId;
		state.type      = 'video';

		videoIframe.style.display = 'none';
		videoIframe.src           = '';
		videoEl.style.display     = '';
		videoEl.src               = url;
		videoEl.load();

		openVideoModal( title );
		videoEl.play().catch( console.error );
	}

	function loadExternalVideo( url, title, episodeId ) {
		audioEl.pause();
		hideBar();

		state.episodeId = episodeId;
		state.type      = 'external-video';

		videoEl.style.display     = 'none';
		videoEl.src               = '';
		videoIframe.style.display = '';
		videoIframe.src           = resolveEmbedUrl( url );

		openVideoModal( title );
	}

	function openVideoModal( title ) {
		videoModal.hidden = false;
		videoModal.setAttribute( 'aria-label', title || 'Video' );
		document.body.classList.add( 'mfx-modal-open' );
		setTimeout( () => videoClose.focus(), 50 );
	}

	function closeVideoModal() {
		videoModal.hidden = true;
		document.body.classList.remove( 'mfx-modal-open' );
		videoEl.pause();
		videoEl.src   = '';
		videoIframe.src = '';
	}

	// ── Playback ──────────────────────────────────────────────────────────────

	function togglePP() {
		if ( audioEl.paused ) audioEl.play().catch( console.error );
		else audioEl.pause();
	}

	function skip( sec ) {
		audioEl.currentTime = clamp( audioEl.currentTime + sec, 0, audioEl.duration || 0 );
	}

	function seekByRatio( ratio ) {
		if ( ! audioEl.duration ) return;
		audioEl.currentTime = ratio * audioEl.duration;
	}

	function getSeekRatio( evt ) {
		const track = progress.querySelector( '.mfx-pbar-progress__track' );
		if ( ! track ) return 0;
		const rect = track.getBoundingClientRect();
		return clamp( ( evt.clientX - rect.left ) / rect.width, 0, 1 );
	}

	// ── Progress saving ───────────────────────────────────────────────────────

	function scheduleSave() {
		if ( ! cfg.ajaxUrl || ! cfg.isLoggedIn || ! state.episodeId ) return;
		clearTimeout( state.saveTimer );
		state.saveTimer = setTimeout( saveProgress, 5000 );
	}

	function buildProgressFormData() {
		const fd = new FormData();
		fd.append( 'action',     'microfix_save_progress' );
		fd.append( 'nonce',      cfg.progressNonce || '' );
		fd.append( 'episode_id', state.episodeId );
		fd.append( 'position',   audioEl.currentTime );
		fd.append( 'duration',   audioEl.duration );
		return fd;
	}

	function saveProgress() {
		if ( ! cfg.ajaxUrl || ! cfg.isLoggedIn ) return;
		if ( ! state.episodeId || state.type !== 'audio' ) return;
		if ( ! audioEl.duration || audioEl.duration <= 0 ) return;

		const fd = buildProgressFormData();

		// sendBeacon is best-effort on unload; use fetch for mid-session saves.
		if ( typeof fetch !== 'undefined' ) {
			fetch( cfg.ajaxUrl, { method: 'POST', body: fd } )
				.then( () => updateCardProgressBar() )
				.catch( () => {} );
		} else if ( typeof navigator.sendBeacon !== 'undefined' ) {
			navigator.sendBeacon( cfg.ajaxUrl, fd );
		}
	}

	/**
	 * Update the progress bar inside the episode card on the current page
	 * so the user can see their progress update live without a page reload.
	 */
	function updateCardProgressBar() {
		if ( ! state.episodeId || ! audioEl.duration ) return;
		const pct = Math.round( ( audioEl.currentTime / audioEl.duration ) * 100 );
		const card = document.querySelector( `.mfx-ep-card[data-episode-id="${ state.episodeId }"]` );
		if ( ! card ) return;

		let bar = card.querySelector( '.mfx-ep-card__progress' );
		if ( ! bar ) {
			// Create progress bar if it doesn't exist yet on this card.
			const fill = document.createElement( 'div' );
			fill.className = 'mfx-ep-card__progress';
			fill.innerHTML = '<div class="mfx-ep-card__progress-fill"></div>';
			card.querySelector( '.mfx-ep-card__body' )?.appendChild( fill );
			bar = fill;
		}

		const fillEl = bar.querySelector( '.mfx-ep-card__progress-fill' );
		if ( fillEl ) fillEl.style.width = pct + '%';
	}

	// Save on page unload — sendBeacon is specifically designed for this.
	window.addEventListener( 'beforeunload', () => {
		if ( ! cfg.ajaxUrl || ! cfg.isLoggedIn ) return;
		if ( ! state.episodeId || state.type !== 'audio' ) return;
		if ( ! audioEl.duration || audioEl.duration <= 0 ) return;
		navigator.sendBeacon?.( cfg.ajaxUrl, buildProgressFormData() );
	} );

	// Also save when tab becomes hidden (mobile background, tab switch).
	document.addEventListener( 'visibilitychange', () => {
		if ( document.visibilityState === 'hidden' ) saveProgress();
	} );

	// ── Event: play button clicks ─────────────────────────────────────────────

	document.addEventListener( 'click', function ( e ) {
		const btn = e.target.closest( '.play-media' );
		if ( ! btn ) return;
		e.preventDefault();

		const url       = btn.dataset.stream;
		const type      = btn.dataset.type;
		const episodeId = parseInt( btn.dataset.episode, 10 ) || 0;
		const title     = btn.dataset.title || '';
		const resumeAt  = parseFloat( btn.dataset.resume || '0' );

		if ( ! url ) return;

		switch ( type ) {
			case 'audio':
				loadAudio( url, title, episodeId, resumeAt );
				break;
			case 'video':
				loadVideo( url, title, episodeId );
				break;
			case 'external-video':
				loadExternalVideo( url, title, episodeId );
				break;
		}
	} );

	// ── Audio events ──────────────────────────────────────────────────────────

	audioEl.addEventListener( 'play',        () => setPlaying( true ) );
	audioEl.addEventListener( 'pause',       () => setPlaying( false ) );
	audioEl.addEventListener( 'ended',       () => { setPlaying( false ); saveProgress(); } );
	audioEl.addEventListener( 'timeupdate',  () => { updateProgress(); scheduleSave(); } );
	audioEl.addEventListener( 'loadedmetadata', updateProgress );

	// ── Control events ────────────────────────────────────────────────────────

	btnPP.addEventListener(  'click', togglePP );
	btnRW.addEventListener(  'click', () => skip( -15 ) );
	btnFW.addEventListener(  'click', () => skip( 15 ) );
	speed.addEventListener(  'change', () => { audioEl.playbackRate = parseFloat( speed.value ) || 1; } );
	btnClose.addEventListener( 'click', () => {
		saveProgress();
		audioEl.pause();
		audioEl.src = '';
		setPlaying( false );
		updateProgress();
		hideBar();
		state.episodeId = null;
		state.type = null;
	} );

	// ── Progress bar seek ─────────────────────────────────────────────────────

	progress.addEventListener( 'pointerdown', e => {
		state.isDragging = true;
		seekByRatio( getSeekRatio( e ) );
		progress.setPointerCapture( e.pointerId );
	} );
	progress.addEventListener( 'pointermove', e => {
		if ( state.isDragging ) seekByRatio( getSeekRatio( e ) );
	} );
	progress.addEventListener( 'pointerup',  () => { state.isDragging = false; } );
	progress.addEventListener( 'keydown', e => {
		if ( e.key === 'ArrowRight' ) { skip(  5 ); e.preventDefault(); }
		if ( e.key === 'ArrowLeft'  ) { skip( -5 ); e.preventDefault(); }
	} );

	// ── Video modal events ────────────────────────────────────────────────────

	if ( videoClose )   videoClose.addEventListener(   'click', closeVideoModal );
	if ( videoBackdrop ) videoBackdrop.addEventListener( 'click', closeVideoModal );

	// ── Global keyboard ───────────────────────────────────────────────────────

	document.addEventListener( 'keydown', e => {
		if ( [ 'INPUT', 'TEXTAREA', 'SELECT' ].includes( document.activeElement?.tagName ) ) return;
		if ( state.type !== 'audio' ) return;
		switch ( e.code ) {
			case 'Space':       e.preventDefault(); togglePP(); break;
			case 'ArrowRight':  skip( 15 );  break;
			case 'ArrowLeft':   skip( -15 ); break;
			case 'KeyM':        audioEl.muted = ! audioEl.muted; break;
		}
	} );

} )();
