/**
 * Gawain AI Video — Admin JavaScript
 *
 * Handles product listing, video generation, deployment,
 * and status polling on the WooCommerce admin page.
 */
(function () {
  'use strict';

  var __ = wp.i18n.__;

  var REST_URL = gawainData.restUrl;
  var NONCE = gawainData.nonce;
  var HAS_CONSENT = !!gawainData.hasConsent;

  var appEl = document.getElementById('gawain-app');
  if (!appEl) return;

  var products = JSON.parse(appEl.dataset.products || '[]');
  var videos = [];
  var generating = null;
  var deploying = null;
  var deleting = null;
  var pollers = {};

  init();

  function init() {
    renderProducts();
    loadExistingVideos();
  }

  // --- API helpers ---

  function apiCall(method, endpoint, body) {
    var opts = {
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': NONCE,
      },
    };
    if (body && method !== 'GET') {
      opts.body = JSON.stringify(body);
    }
    return fetch(REST_URL + endpoint, opts).then(function (r) {
      return r.json();
    });
  }

  // --- Load existing videos ---

  function loadExistingVideos() {
    if (products.length === 0) return;
    var ids = products.map(function (p) { return p.id; }).join(',');
    apiCall('GET', 'videos?product_ids=' + encodeURIComponent(ids)).then(function (data) {
      if (data.success && data.videos) {
        videos = data.videos.map(function (v) {
          var product = products.find(function (p) { return p.id === v.productId; });
          return {
            videoId: v.jobId,
            productId: v.productId,
            productTitle: product ? product.title : '',
            status: v.status,
            progress: v.progress || statusToProgress(v.status),
            previewUrl: v.videoUrl || null,
            deployed: v.deployed,
          };
        });
        renderVideos();

        // Resume polling for in-progress
        videos.forEach(function (v) {
          if (v.status === 'pending' || v.status === 'processing') {
            startPolling(v.videoId);
          }
        });
      }
    });
  }

  // --- Products rendering ---

  function renderProducts() {
    var container = document.getElementById('gawain-products');
    if (!container) return;

    if (products.length === 0) {
      container.innerHTML = '<div class="gawain-empty"><p>' + escapeHtml(__('No products found', 'gawain-ai-video')) + '</p><p style="font-size:13px">' + escapeHtml(__('Please add products in WooCommerce.', 'gawain-ai-video')) + '</p></div>';
      return;
    }

    container.innerHTML = products.map(function (p) {
      var videoCount = videos.filter(function (v) { return v.productId === p.id; }).length;
      var hasActive = videos.some(function (v) {
        return v.productId === p.id && (v.status === 'pending' || v.status === 'processing');
      });
      var hasVideo = videoCount > 0;

      var badge = '';
      if (hasActive) {
        badge = '<div class="gawain-product-badge gawain-badge-active">' + escapeHtml(__('Generating', 'gawain-ai-video')) + '</div>';
      } else if (videoCount > 0) {
        /* translators: %d: number of videos */
        badge = '<div class="gawain-product-badge gawain-badge-count">' + escapeHtml(wp.i18n.sprintf(__('%d videos', 'gawain-ai-video'), videoCount)) + '</div>';
      }

      var thumb = p.thumb
        ? '<img src="' + escapeAttr(p.thumb) + '" alt="' + escapeAttr(p.title) + '">'
        : '<div class="gawain-no-image">' + escapeHtml(__('No image', 'gawain-ai-video')) + '</div>';

      var price = p.price ? '<p class="gawain-product-price">&yen;' + Number(p.price).toLocaleString() + '</p>' : '';

      var btn;
      if (!p.image) {
        btn = '<span style="font-size:10px;color:#9ca3af;text-align:center;display:block">' + escapeHtml(__('No image', 'gawain-ai-video')) + '</span>';
      } else {
        var disabled = generating === p.id || hasActive ? ' disabled' : '';
        var cls = hasVideo ? 'gawain-btn gawain-btn-secondary' : 'gawain-btn gawain-btn-primary';
        var label = generating === p.id
          ? __('Starting...', 'gawain-ai-video')
          : (hasVideo ? __('Regenerate Video', 'gawain-ai-video') : __('Generate Video', 'gawain-ai-video'));
        btn = '<button class="' + cls + '" data-generate="' + escapeAttr(p.id) + '"' + disabled + '>' + escapeHtml(label) + '</button>';
      }

      return '<div class="gawain-product-card">'
        + '<div class="gawain-product-thumb">' + thumb + badge + '</div>'
        + '<div class="gawain-product-info">'
        + '<h3 class="gawain-product-name">' + escapeHtml(p.title) + '</h3>'
        + price
        + btn
        + '</div></div>';
    }).join('');

    // Bind generate buttons
    container.querySelectorAll('[data-generate]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        handleGenerate(btn.dataset.generate);
      });
    });
  }

  // --- Videos rendering ---

  function renderVideos() {
    var section = document.getElementById('gawain-videos-section');
    var container = document.getElementById('gawain-videos');
    if (!section || !container) return;

    section.style.display = videos.length > 0 ? '' : 'none';

    container.innerHTML = videos.map(function (v) {
      var preview;
      if (v.status === 'completed' && v.previewUrl) {
        preview = '<video src="' + escapeAttr(v.previewUrl) + '" autoplay loop muted playsinline></video>'
          + '<a class="gawain-play-link" href="' + escapeAttr(v.previewUrl) + '" target="_blank" rel="noopener">'
          + '<svg width="14" height="14" viewBox="0 0 16 16" fill="white"><path d="M4 2l10 6-10 6V2z"/></svg></a>';
      } else if (v.status === 'failed') {
        preview = '<div class="gawain-failed-overlay">' + escapeHtml(__('Generation failed', 'gawain-ai-video')) + '</div>';
      } else {
        var phase = v.progress < 30
          ? __('Generating 3D', 'gawain-ai-video')
          : (v.progress < 70 ? __('Creating video', 'gawain-ai-video') : __('Finalizing', 'gawain-ai-video'));
        preview = '<div class="gawain-progress-overlay">'
          + '<div class="gawain-spinner"></div>'
          + '<span class="gawain-progress-text">' + (v.status === 'pending' ? escapeHtml(__('Queued...', 'gawain-ai-video')) : v.progress + '%') + '</span>'
          + '<div class="gawain-progress-bar"><div class="gawain-progress-fill" style="width:' + v.progress + '%"></div></div>'
          + '<p class="gawain-progress-phase">' + escapeHtml(phase) + '</p>'
          + '<button class="gawain-btn gawain-btn-danger" data-delete="' + escapeAttr(v.videoId) + '" style="margin-top:8px;width:auto;background:transparent;border:none;color:rgba(255,255,255,0.6);font-size:10px;text-decoration:underline">' + escapeHtml(__('Cancel', 'gawain-ai-video')) + '</button>'
          + '</div>';
      }

      var actions = '';
      if (v.status === 'completed' && v.previewUrl) {
        if (v.deployed) {
          actions = '<div class="gawain-video-actions-row">'
            + '<div class="gawain-btn gawain-btn-deployed">&#10003; ' + escapeHtml(__('Placed', 'gawain-ai-video')) + '</div>'
            + '<a class="gawain-btn-icon" href="' + escapeAttr(v.previewUrl) + '" target="_blank" rel="noopener">'
            + '<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M4 2l10 6-10 6V2z"/></svg></a>'
            + '</div>'
            + '<button class="gawain-btn gawain-btn-danger" data-undeploy="' + escapeAttr(v.videoId) + '">' + escapeHtml(__('Remove from site', 'gawain-ai-video')) + '</button>';
        } else {
          actions = '<div class="gawain-video-actions-row">'
            + '<button class="gawain-btn gawain-btn-success" data-deploy="' + escapeAttr(v.videoId) + '">' + escapeHtml(__('Place on site', 'gawain-ai-video')) + '</button>'
            + '<a class="gawain-btn-icon" href="' + escapeAttr(v.previewUrl) + '" target="_blank" rel="noopener">'
            + '<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M4 2l10 6-10 6V2z"/></svg></a>'
            + '</div>'
            + '<button class="gawain-btn gawain-btn-danger" data-delete="' + escapeAttr(v.videoId) + '">' + escapeHtml(__('Delete', 'gawain-ai-video')) + '</button>';
        }
      } else if (v.status === 'failed') {
        actions = '<button class="gawain-btn gawain-btn-danger" style="font-size:12px" data-retry="' + escapeAttr(v.videoId) + '">' + escapeHtml(__('Retry', 'gawain-ai-video')) + '</button>'
          + '<button class="gawain-btn gawain-btn-danger" data-delete="' + escapeAttr(v.videoId) + '">' + escapeHtml(__('Delete', 'gawain-ai-video')) + '</button>';
      } else if (v.status === 'pending' || v.status === 'processing') {
        actions = '<button class="gawain-btn gawain-btn-secondary" data-delete="' + escapeAttr(v.videoId) + '">' + escapeHtml(__('Cancel', 'gawain-ai-video')) + '</button>';
      }

      return '<div class="gawain-video-card">'
        + '<div class="gawain-video-preview">' + preview + '</div>'
        + '<div class="gawain-video-info">'
        + '<h3 class="gawain-video-title">' + escapeHtml(v.productTitle) + '</h3>'
        + '<div class="gawain-video-actions">' + actions + '</div>'
        + '</div></div>';
    }).join('');

    bindVideoActions(container);
  }

  function bindVideoActions(container) {
    container.querySelectorAll('[data-deploy]').forEach(function (btn) {
      btn.addEventListener('click', function () { handleDeploy(btn.dataset.deploy); });
    });
    container.querySelectorAll('[data-undeploy]').forEach(function (btn) {
      btn.addEventListener('click', function () { handleUndeploy(btn.dataset.undeploy); });
    });
    container.querySelectorAll('[data-delete]').forEach(function (btn) {
      btn.addEventListener('click', function () { handleDelete(btn.dataset.delete); });
    });
    container.querySelectorAll('[data-retry]').forEach(function (btn) {
      btn.addEventListener('click', function () { handleRetry(btn.dataset.retry); });
    });
  }

  // --- Handlers ---

  function handleGenerate(productId) {
    if (!HAS_CONSENT) {
      showToast(__('External processing is not enabled. Please enable it in the Settings tab.', 'gawain-ai-video'), 'error');
      return;
    }
    generating = productId;
    renderProducts();

    apiCall('POST', 'generate', { product_id: parseInt(productId, 10) }).then(function (data) {
      generating = null;
      if (data.jobId) {
        videos.unshift({
          videoId: data.jobId,
          productId: data.productId ? String(data.productId) : productId,
          productTitle: data.productTitle || '',
          status: 'pending',
          progress: 5,
          previewUrl: null,
          deployed: false,
        });
        /* translators: %s: product title */
        showToast(wp.i18n.sprintf(__('Started generating video for "%s"', 'gawain-ai-video'), data.productTitle || ''), 'info');
        startPolling(data.jobId);
      } else {
        showToast(data.message || __('Failed to start video generation', 'gawain-ai-video'), 'error');
      }
      renderProducts();
      renderVideos();
    }).catch(function () {
      generating = null;
      showToast(__('Failed to start video generation', 'gawain-ai-video'), 'error');
      renderProducts();
    });
  }

  function handleDeploy(videoId) {
    if (!HAS_CONSENT) {
      showToast(__('External processing is not enabled. Please enable it in the Settings tab.', 'gawain-ai-video'), 'error');
      return;
    }
    deploying = videoId;
    renderVideos();

    apiCall('POST', 'deploy', { videoId: videoId }).then(function (data) {
      deploying = null;
      if (data.success) {
        var v = videos.find(function (x) { return x.videoId === videoId; });
        if (v) v.deployed = true;
        showToast(__('Placed on site', 'gawain-ai-video'), 'success');
      } else {
        showToast(data.message || __('Failed to place on site', 'gawain-ai-video'), 'error');
      }
      renderVideos();
    }).catch(function () {
      deploying = null;
      showToast(__('Failed to place on site', 'gawain-ai-video'), 'error');
      renderVideos();
    });
  }

  function handleUndeploy(videoId) {
    if (!HAS_CONSENT) {
      showToast(__('External processing is not enabled. Please enable it in the Settings tab.', 'gawain-ai-video'), 'error');
      return;
    }
    deploying = videoId;
    renderVideos();

    apiCall('POST', 'undeploy', { videoId: videoId }).then(function (data) {
      deploying = null;
      if (data.success) {
        var v = videos.find(function (x) { return x.videoId === videoId; });
        if (v) v.deployed = false;
        showToast(__('Removed from site', 'gawain-ai-video'), 'info');
      } else {
        showToast(data.message || __('Failed to remove from site', 'gawain-ai-video'), 'error');
      }
      renderVideos();
    }).catch(function () {
      deploying = null;
      showToast(__('Failed to remove from site', 'gawain-ai-video'), 'error');
      renderVideos();
    });
  }

  function handleDelete(videoId) {
    deleting = videoId;
    renderVideos();

    // Stop polling
    if (pollers[videoId]) {
      clearInterval(pollers[videoId]);
      delete pollers[videoId];
    }

    return apiCall('POST', 'delete', { videoId: videoId }).then(function (data) {
      deleting = null;
      if (data.success) {
        videos = videos.filter(function (v) { return v.videoId !== videoId; });
        showToast(__('Deleted', 'gawain-ai-video'), 'info');
      } else {
        showToast(data.message || __('Failed to delete', 'gawain-ai-video'), 'error');
      }
      renderProducts();
      renderVideos();
      return data.success;
    }).catch(function () {
      deleting = null;
      showToast(__('Failed to delete', 'gawain-ai-video'), 'error');
      renderVideos();
      return false;
    });
  }

  function handleRetry(videoId) {
    var v = videos.find(function (x) { return x.videoId === videoId; });
    if (!v) return;
    var productId = v.productId;
    handleDelete(videoId).then(function (success) {
      if (success) handleGenerate(productId);
    });
  }

  // --- Polling ---

  function startPolling(jobId) {
    if (pollers[jobId]) return;
    pollers[jobId] = setInterval(function () {
      apiCall('GET', 'job/' + encodeURIComponent(jobId)).then(function (data) {
        if (!data.status) return;
        var v = videos.find(function (x) { return x.videoId === jobId; });
        if (v) {
          v.status = data.status;
          v.progress = data.progress || v.progress;
          if (data.previewUrl) v.previewUrl = data.previewUrl;
          if (data.downloadUrl && !v.previewUrl) v.previewUrl = data.downloadUrl;
          renderVideos();
          renderProducts();
        }
        if (data.status === 'completed' || data.status === 'failed') {
          clearInterval(pollers[jobId]);
          delete pollers[jobId];
          if (data.status === 'completed') {
            showToast(__('Video generation complete', 'gawain-ai-video'), 'success');
          }
        }
      }).catch(function () {
        clearInterval(pollers[jobId]);
        delete pollers[jobId];
      });
    }, 5000);

    // Timeout after 10 minutes
    setTimeout(function () {
      if (pollers[jobId]) {
        clearInterval(pollers[jobId]);
        delete pollers[jobId];
      }
    }, 600000);
  }

  // --- Toast ---

  function showToast(message, type) {
    var container = document.getElementById('gawain-toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'gawain-toast-container';
      container.className = 'gawain-toast-container';
      document.body.appendChild(container);
    }

    var el = document.createElement('div');
    el.className = 'gawain-toast gawain-toast-' + (type || 'info');
    el.innerHTML = '<span>' + escapeHtml(message) + '</span>'
      + '<button class="gawain-toast-close">&times;</button>';

    el.querySelector('.gawain-toast-close').addEventListener('click', function () {
      el.remove();
    });

    container.appendChild(el);
    setTimeout(function () { el.remove(); }, 5000);
  }

  // --- Utils ---

  function statusToProgress(status) {
    switch (status) {
      case 'completed': return 100;
      case 'processing': return 50;
      case 'failed': return 0;
      default: return 10;
    }
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function escapeAttr(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }
})();
