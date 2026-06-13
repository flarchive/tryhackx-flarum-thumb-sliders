import Component from 'flarum/common/Component';

/**
 * ThumbSlider - Slick-style fade+scale image slider.
 *
 * Mimics slick carousel behavior:
 *   fade: true, speed: 900, cssEase: cubic-bezier(0.7, 0, 0.3, 1),
 *   autoplaySpeed: 1200, with scale(1.2)->scale(1) on active
 *
 * Uses direct DOM manipulation (Flarum SubtreeRetainer blocks m.redraw).
 * Lazy-loads via IntersectionObserver. The slider stays hidden until its FIRST
 * image actually loads, then reveals itself — so a broken or slow-failing image
 * never flashes an empty loading box (see the `--probing` / reveal() flow).
 */
// Module-level cache of image URLs that have already loaded successfully in this
// session, shared across all slider instances and remounts. When the discussion
// list re-renders (e.g. live search typing), a remounted slider can show an
// already-seen image instantly from the browser cache — no probe Image(), no
// re-fade, no perceived re-download.
const loadedUrlCache = new Set();

// Companion to loadedUrlCache: URLs that FAILED to load this session (404,
// removed attachment, blocked/rate-limited host, …). Remembering them lets an
// in-session remount (live-search re-render) skip re-probing a URL we already
// know is dead, and lets oninit drop such images up front so a multi-image
// slider shows only its working ones. It is a pure efficiency/coordination cache
// (cleared on a hard reload); what actually prevents the loading-box flash is the
// `--probing` / reveal() flow, which never paints a box until an image loads.
const failedUrlCache = new Set();

export default class ThumbSlider extends Component {
  oninit(vnode) {
    super.oninit(vnode);

    // Drop images already known to be broken this session, so an in-session
    // re-render (live search) doesn't re-probe a dead URL; a multi-image slider
    // then shows only its still-working images.
    this.images = (this.attrs.images || [])
      .map(url => url.replace(/\\/g, '/'))
      .filter(url => !failedUrlCache.has(url));
    this.currentIndex = 0;
    this.isVisible = false;
    this.revealed = false;
    this.firstLoadStarted = false;
    this.loadedImages = new Set();
    this.autoplayTimer = null;
    this.outgoingTimer = null;
    this.observer = null;
    this.dom = null;
  }

  view() {
    const sliderWidth = this.attrs.sliderWidth || 150;
    const imageCount = this.images.length;

    if (imageCount === 0) {
      return this.renderFallback(sliderWidth);
    }

    // Starts in the `--probing` state (display:none): it occupies no space and
    // paints no box until the first image actually loads (see loadFirstImage /
    // reveal). A broken or slow-failing image therefore never flashes a box.
    return (
      <div className="ThumbSlider ThumbSlider--probing" style={{ width: sliderWidth + 'px' }}>
        <div className="ThumbSlider__track">
          {this.images.map((src, i) => (
            <div className={'ThumbSlider__item' + (i === 0 ? ' ThumbSlider__item--active' : '')} key={i}>
              <img className="ThumbSlider__img" alt="" decoding="async" />
            </div>
          ))}
        </div>
        {imageCount > 1 && (
          <span className="ThumbSlider__counter">1/{imageCount}</span>
        )}
      </div>
    );
  }

  renderFallback(sliderWidth) {
    const mode = this.attrs.fallbackMode;
    const url = this.attrs.fallbackUrl;

    if (mode === 'custom' && url) {
      return (
        <div className="ThumbSlider ThumbSlider--fallback ThumbSlider--fallback-custom" style={{ width: sliderWidth + 'px' }}>
          <div className="ThumbSlider__track">
            <div className="ThumbSlider__item ThumbSlider__item--active">
              <img className="ThumbSlider__img" src={url} alt="" decoding="async" />
            </div>
          </div>
        </div>
      );
    }

    if (mode === 'default') {
      return (
        <div className="ThumbSlider ThumbSlider--fallback ThumbSlider--fallback-default" style={{ width: sliderWidth + 'px' }}>
          <div className="ThumbSlider__track">
            <div className="ThumbSlider__item ThumbSlider__item--active">
              <div className="ThumbSlider__placeholder" aria-hidden="true" />
            </div>
          </div>
        </div>
      );
    }

    return null;
  }

  oncreate(vnode) {
    super.oncreate(vnode);
    this.dom = vnode.dom;

    // view() can render nothing (null) when there are no images and the fallback
    // mode is 'none' — bail before touching the DOM.
    if (!this.dom) return;

    // Static fallback variants (no extracted images) are shown immediately and
    // mark the row so coordinating extensions (e.g. homepage-blocks) see a thumb.
    const isFallback = this.dom.classList.contains('ThumbSlider--fallback');
    if (isFallback) {
      this.markRowHasThumb();
      return;
    }

    // Image slider: starts hidden (`--probing`) and is revealed only once the
    // FIRST image has actually loaded, so a broken / slow-failing image never
    // flashes a loading box. Observe the VISIBLE row rather than the hidden
    // slider (an IntersectionObserver can't see a display:none element), so
    // lazy-loading near the viewport still works. `has-ThumbSlider` is added on
    // reveal — until then the row legitimately has no thumb to coordinate with.
    const observeTarget = this.dom.closest('.DiscussionListItem-content') || this.dom.parentElement || this.dom;
    this.setupIntersectionObserver(observeTarget);
  }

  onbeforeupdate() {
    return false;
  }

  onremove(vnode) {
    super.onremove(vnode);
    this.cleanup();
  }

  setupIntersectionObserver(element) {
    if (typeof IntersectionObserver === 'undefined') {
      this.isVisible = true;
      this.firstLoadStarted = true;
      this.loadFirstImage();
      return;
    }

    this.observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            this.isVisible = true;
            if (!this.firstLoadStarted) {
              this.firstLoadStarted = true;
              this.loadFirstImage();
            }
            this.startAutoplay();
          } else {
            this.isVisible = false;
            this.stopAutoplay();
          }
        });
      },
      { rootMargin: '200px 0px', threshold: 0.1 }
    );

    this.observer.observe(element);
  }

  loadFirstImage() {
    if (this.images.length === 0 || !this.dom) return;

    const firstItem = this.dom.querySelector('.ThumbSlider__item');
    if (!firstItem) return;
    const imgEl = firstItem.querySelector('.ThumbSlider__img');
    if (!imgEl) return;

    const url = this.images[0];

    // Already loaded earlier this session — reveal instantly from the browser
    // cache, skipping the probe Image().
    if (loadedUrlCache.has(url)) {
      this.loadedImages.add(0);
      imgEl.src = url;
      this.reveal();
      if (this.isVisible) this.startAutoplay();
      if (this.images.length > 1) {
        this.preloadImage(1);
      }
      return;
    }

    // Probe the first image INVISIBLY. The slider stays hidden (`--probing`) and
    // shows nothing — no box, no spinner, no reserved height — until this
    // resolves. On success we reveal it; on failure it is either never shown
    // (mode 'none') or swapped for the fallback. This is what stops a broken or
    // slow-failing image from flashing an empty loading box on refresh / search.
    const img = new Image();
    img.onload = () => {
      // The slider may have been removed (list re-rendered) while loading.
      if (!this.dom) return;
      loadedUrlCache.add(url);
      this.loadedImages.add(0);
      imgEl.src = url;
      this.reveal();
      if (this.isVisible) this.startAutoplay();
      if (this.images.length > 1) {
        this.preloadImage(1);
      }
    };
    img.onerror = () => {
      // Remember the failure so a later remount (refresh / live search) drops
      // this URL up front (oninit filter) instead of re-probing it.
      failedUrlCache.add(url);
      this.handleImageLoadFailure();
    };
    img.src = url;
  }

  // Reveal the slider once its first image is ready: drop the hidden `--probing`
  // state, show the card (`--ready`), and signal coordinating extensions.
  reveal() {
    if (!this.dom || this.revealed) return;
    this.revealed = true;
    this.dom.classList.remove('ThumbSlider--probing');
    this.dom.classList.add('ThumbSlider--ready');
    this.markRowHasThumb();
  }

  // Signal coordinating extensions (e.g. homepage-blocks) that this row now has a
  // visible thumbnail. Called on reveal / fallback — never while still probing.
  markRowHasThumb() {
    const contentEl = this.dom && this.dom.closest('.DiscussionListItem-content');
    if (contentEl) contentEl.classList.add('has-ThumbSlider');
  }

  handleImageLoadFailure() {
    if (!this.dom) return;

    this.stopAutoplay();

    const mode = this.attrs.fallbackMode;
    const url = this.attrs.fallbackUrl;

    if (mode === 'custom' && url) {
      // Reveal a static custom fallback in place of the failed image.
      this.replaceTrackWithFallback(
        '<img class="ThumbSlider__img" src="' + this.escapeAttr(url) + '" alt="" decoding="async" />'
      );
      this.dom.classList.remove('ThumbSlider--probing');
      this.dom.classList.add('ThumbSlider--fallback', 'ThumbSlider--fallback-custom');
      this.markRowHasThumb();
    } else if (mode === 'default') {
      this.replaceTrackWithFallback('<div class="ThumbSlider__placeholder" aria-hidden="true"></div>');
      this.dom.classList.remove('ThumbSlider--probing');
      this.dom.classList.add('ThumbSlider--fallback', 'ThumbSlider--fallback-default');
      this.markRowHasThumb();
    }
    // mode === 'none': the slider was never revealed — it simply stays hidden
    // (`--probing` keeps it display:none, taking no space). No `has-ThumbSlider`
    // was ever added, so the row already renders thumb-less. Nothing to do.

    // Hide counter (single fallback slide doesn't need it).
    const counter = this.dom.querySelector('.ThumbSlider__counter');
    if (counter) counter.style.display = 'none';
  }

  replaceTrackWithFallback(innerHtml) {
    const track = this.dom.querySelector('.ThumbSlider__track');
    if (!track) return;
    track.innerHTML = '<div class="ThumbSlider__item ThumbSlider__item--active">' + innerHtml + '</div>';
  }

  escapeAttr(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  preloadImage(index) {
    if (index >= this.images.length || this.loadedImages.has(index)) return;

    const url = this.images[index];

    // Skip a slide already known to be broken this session — don't re-probe it.
    if (failedUrlCache.has(url)) return;

    // Push the resolved URL into the matching slide. Guards `this.dom` because
    // the slider can be unmounted (live search re-render) while the image is
    // still loading — without this the late onload threw
    // "Cannot read properties of null (reading 'querySelectorAll')".
    const apply = () => {
      if (!this.dom) return;
      this.loadedImages.add(index);
      const items = this.dom.querySelectorAll('.ThumbSlider__item');
      if (items[index]) {
        const imgEl = items[index].querySelector('.ThumbSlider__img');
        if (imgEl) imgEl.src = url;
      }
    };

    if (loadedUrlCache.has(url)) {
      apply();
      return;
    }

    const img = new Image();
    img.onload = () => {
      loadedUrlCache.add(url);
      apply();
    };
    img.onerror = () => {
      // Record the failure (the slide keeps no src, so it stays blank rather
      // than showing a broken-image icon) so a remount drops it via oninit.
      failedUrlCache.add(url);
    };
    img.src = url;
  }

  startAutoplay() {
    if (this.autoplayTimer || this.images.length <= 1 || !this.dom || !this.revealed) return;

    const speed = this.attrs.autoplaySpeed || 1200;

    this.autoplayTimer = setInterval(() => {
      if (!this.isVisible || !this.dom) {
        this.stopAutoplay();
        return;
      }

      const items = this.dom.querySelectorAll('.ThumbSlider__item');
      if (items.length === 0) return;

      const oldIndex = this.currentIndex;

      // Clear any previous outgoing class
      if (this.outgoingTimer) {
        clearTimeout(this.outgoingTimer);
        this.outgoingTimer = null;
      }
      items.forEach(item => item.classList.remove('ThumbSlider__item--outgoing'));

      // Mark old slide as outgoing (stays visible at opacity 1 underneath)
      if (items[oldIndex]) {
        items[oldIndex].classList.remove('ThumbSlider__item--active');
        items[oldIndex].classList.add('ThumbSlider__item--outgoing');
      }

      // Advance
      this.currentIndex = (this.currentIndex + 1) % this.images.length;

      // Activate new slide (fades in on top with transition)
      if (items[this.currentIndex]) {
        items[this.currentIndex].classList.add('ThumbSlider__item--active');
      }

      // Remove outgoing class after the CSS transition finishes (900ms + buffer)
      this.outgoingTimer = setTimeout(() => {
        if (items[oldIndex]) {
          items[oldIndex].classList.remove('ThumbSlider__item--outgoing');
        }
        this.outgoingTimer = null;
      }, 950);

      // Update counter
      const counter = this.dom.querySelector('.ThumbSlider__counter');
      if (counter) {
        counter.textContent = (this.currentIndex + 1) + '/' + this.images.length;
      }

      // Preload next
      const next = (this.currentIndex + 1) % this.images.length;
      this.preloadImage(next);
    }, speed);
  }

  stopAutoplay() {
    if (this.autoplayTimer) {
      clearInterval(this.autoplayTimer);
      this.autoplayTimer = null;
    }
  }

  cleanup() {
    this.stopAutoplay();
    if (this.outgoingTimer) {
      clearTimeout(this.outgoingTimer);
      this.outgoingTimer = null;
    }
    if (this.observer) {
      this.observer.disconnect();
      this.observer = null;
    }
    this.dom = null;
  }
}
