/**
 * Gawain AI Video — Storefront Video Carousel
 *
 * Renders a horizontal video carousel on WooCommerce product pages.
 *
 * Features:
 * - Autoplay, loop, muted videos
 * - Tap to unmute / toggle play
 * - Mute/unmute button
 * - Expand to fullscreen modal
 * - Responsive horizontal scroll
 */
(function () {
  'use strict';

  var config = window.gawainStorefront || {};
  var API_BASE = config.apiBase || 'https://gawain.nogeass.com';
  var SITE = config.site || window.location.hostname;

  // SVG icons
  var ICON_MUTED = '<svg width="14" height="14" viewBox="0 0 24 24" fill="white"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>';
  var ICON_UNMUTED = '<svg width="14" height="14" viewBox="0 0 24 24" fill="white"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>';
  var ICON_EXPAND = '<svg width="14" height="14" viewBox="0 0 24 24" fill="white"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>';
  var ICON_CLOSE = '<svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>';

  // Find all containers and initialize
  function initAll() {
    var containers = document.querySelectorAll('.gawain-video-section');
    containers.forEach(function (el) {
      if (el.dataset.gawainInit) return;
      el.dataset.gawainInit = '1';
      initContainer(el);
    });
  }

  function initContainer(el) {
    var productId = el.dataset.productId;
    if (!productId) return;

    fetch(API_BASE + '/api/wordpress/storefront-videos?site=' + encodeURIComponent(SITE) + '&productId=' + encodeURIComponent(productId))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.videos || data.videos.length === 0) return;
        renderCarousel(el, data.videos);
      })
      .catch(function () {});
  }

  function renderCarousel(container, videos) {
    // Heading
    var heading = document.createElement('h3');
    heading.className = 'gawain-storefront-heading';
    heading.textContent = '\u30D7\u30ED\u30E2\u30FC\u30B7\u30E7\u30F3\u52D5\u753B'; // プロモーション動画
    container.appendChild(heading);

    // Scroll wrapper
    var scroll = document.createElement('div');
    scroll.className = 'gawain-storefront-scroll';
    var grid = document.createElement('div');
    grid.className = 'gawain-storefront-grid';

    videos.forEach(function (v) {
      var card = document.createElement('div');
      card.className = 'gawain-storefront-card';

      var video = document.createElement('video');
      video.src = v.url;
      video.autoplay = true;
      video.loop = true;
      video.muted = true;
      video.playsInline = true;
      video.setAttribute('playsinline', '');
      card.appendChild(video);

      // Title
      if (v.title) {
        var titleBar = document.createElement('div');
        titleBar.className = 'gawain-storefront-title';
        titleBar.textContent = v.title;
        card.appendChild(titleBar);
      }

      // Overlay controls
      var overlay = document.createElement('div');
      overlay.className = 'gawain-storefront-overlay';

      var muteBtn = document.createElement('button');
      muteBtn.innerHTML = ICON_MUTED;
      muteBtn.setAttribute('aria-label', 'ミュート切替');
      muteBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        video.muted = !video.muted;
        muteBtn.innerHTML = video.muted ? ICON_MUTED : ICON_UNMUTED;
      });

      var expandBtn = document.createElement('button');
      expandBtn.innerHTML = ICON_EXPAND;
      expandBtn.setAttribute('aria-label', '拡大表示');
      expandBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        openModal(v.url, video.currentTime, video.muted);
      });

      overlay.appendChild(muteBtn);
      overlay.appendChild(expandBtn);
      card.appendChild(overlay);

      // Tap to unmute (first tap), then toggle play/pause
      var firstTap = true;
      card.addEventListener('click', function () {
        if (firstTap && video.muted) {
          video.muted = false;
          muteBtn.innerHTML = ICON_UNMUTED;
          firstTap = false;
        } else {
          if (video.paused) {
            video.play();
          } else {
            video.pause();
          }
        }
      });

      grid.appendChild(card);
    });

    scroll.appendChild(grid);
    container.appendChild(scroll);
  }

  function openModal(videoUrl, currentTime, isMuted) {
    var backdrop = document.createElement('div');
    backdrop.className = 'gawain-modal-backdrop';

    var content = document.createElement('div');
    content.className = 'gawain-modal-content';

    var video = document.createElement('video');
    video.src = videoUrl;
    video.controls = true;
    video.autoplay = true;
    video.muted = isMuted;
    video.currentTime = currentTime || 0;
    content.appendChild(video);

    var closeBtn = document.createElement('button');
    closeBtn.className = 'gawain-modal-close';
    closeBtn.innerHTML = ICON_CLOSE;
    closeBtn.addEventListener('click', close);
    content.appendChild(closeBtn);

    backdrop.appendChild(content);
    backdrop.addEventListener('click', function (e) {
      if (e.target === backdrop) close();
    });

    document.body.appendChild(backdrop);

    function onKey(e) {
      if (e.key === 'Escape') close();
    }
    document.addEventListener('keydown', onKey);

    function close() {
      video.pause();
      backdrop.remove();
      document.removeEventListener('keydown', onKey);
    }
  }

  // Init on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }

  // Watch for dynamically added containers
  if (typeof MutationObserver !== 'undefined') {
    new MutationObserver(function () { initAll(); })
      .observe(document.body, { childList: true, subtree: true });
  }
})();
