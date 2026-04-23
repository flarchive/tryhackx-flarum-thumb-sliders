import Component from 'flarum/common/Component';

/**
 * ThumbSlider - Slick-style fade+scale image slider.
 *
 * Mimics slick carousel behavior:
 *   fade: true, speed: 900, cssEase: cubic-bezier(0.7, 0, 0.3, 1),
 *   autoplaySpeed: 1200, with scale(1.2)->scale(1) on active
 *
 * Uses direct DOM manipulation (Flarum SubtreeRetainer blocks m.redraw).
 * IntersectionObserver for lazy loading.
 */
export default class ThumbSlider extends Component {
  oninit(vnode) {
    super.oninit(vnode);

    this.images = (this.attrs.images || []).map(url => url.replace(/\\/g, '/'));
    this.currentIndex = 0;
    this.isVisible = false;
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

    return (
      <div className="ThumbSlider ThumbSlider--loading" style={{ width: sliderWidth + 'px' }}>
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

    const sliderWidth = this.attrs.sliderWidth || 150;
    const contentEl = this.dom.closest('.DiscussionListItem-content');
    if (contentEl) {
      contentEl.style.setProperty('--thumb-slider-width', sliderWidth + 'px');
      contentEl.classList.add('has-ThumbSlider');
    }

    // Fallback variants are static – no autoplay, no lazy loading needed.
    const isFallback = this.dom.classList.contains('ThumbSlider--fallback');
    if (!isFallback) {
      this.setupIntersectionObserver(this.dom);
    }
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
      this.loadFirstImage();
      return;
    }

    this.observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            if (this.loadedImages.size === 0) {
              this.loadFirstImage();
            }
            this.isVisible = true;
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

    const img = new Image();
    img.onload = () => {
      this.loadedImages.add(0);
      imgEl.src = this.images[0];
      this.dom.classList.remove('ThumbSlider--loading');

      if (this.images.length > 1) {
        this.preloadImage(1);
      }
    };
    img.onerror = () => {
      this.handleImageLoadFailure();
    };
    img.src = this.images[0];
  }

  handleImageLoadFailure() {
    if (!this.dom) return;

    const mode = this.attrs.fallbackMode;
    const url = this.attrs.fallbackUrl;

    // Stop autoplay - we're switching to a static fallback
    this.stopAutoplay();
    this.dom.classList.remove('ThumbSlider--loading');

    if (mode === 'custom' && url) {
      this.replaceTrackWithFallback(
        '<img class="ThumbSlider__img" src="' + this.escapeAttr(url) + '" alt="" decoding="async" />'
      );
      this.dom.classList.add('ThumbSlider--fallback', 'ThumbSlider--fallback-custom');
    } else if (mode === 'default') {
      this.replaceTrackWithFallback('<div class="ThumbSlider__placeholder" aria-hidden="true"></div>');
      this.dom.classList.add('ThumbSlider--fallback', 'ThumbSlider--fallback-default');
    } else {
      // mode === 'none': hide entire slider and restore default content layout
      this.dom.style.display = 'none';
      const contentEl = this.dom.closest('.DiscussionListItem-content');
      if (contentEl) contentEl.classList.remove('has-ThumbSlider');
    }

    // Hide counter (single fallback slide doesn't need it)
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

    const img = new Image();
    img.onload = () => {
      this.loadedImages.add(index);
      const items = this.dom.querySelectorAll('.ThumbSlider__item');
      if (items[index]) {
        const imgEl = items[index].querySelector('.ThumbSlider__img');
        if (imgEl) imgEl.src = this.images[index];
      }
    };
    img.src = this.images[index];
  }

  startAutoplay() {
    if (this.autoplayTimer || this.images.length <= 1 || !this.dom) return;

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
