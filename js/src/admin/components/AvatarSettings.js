import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Select from 'flarum/common/components/Select';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';

/**
 * AvatarSettings — shared "together or separate" avatar control.
 * ------------------------------------------------------------------
 * Identical copy lives in BOTH:
 *   - tryhackx/flarum-thumb-sliders/js/src/admin/components/AvatarSettings.js
 *   - tryhackx/flarum-topic-rating/js/src/admin/components/AvatarSettings.js
 * Keep them in sync.
 *
 * Lets the admin choose how the author avatar behaves in the discussion list,
 * SEPARATELY for desktop and mobile. The two extensions write the SAME neutral
 * `tryhackx-avatars.*` setting keys, so a change made on one extension's page
 * is immediately reflected on the other's (the in-memory `app.data.settings`
 * is updated and the value is persisted at once, bypassing the page's Save
 * button — same immediate-apply pattern as the fallback image library).
 *
 * "Smart" bits:
 *   - It self-detects whether Thumb Sliders is installed & enabled. When it is
 *     not, the "replace with thumbnail" modes have no thumbnail to use, so the
 *     UI says so and the per-device hint explains the actual resulting
 *     behaviour ("avatar stays" vs "avatar hidden").
 */

const MODE_KEYS = {
  desktop: 'tryhackx-avatars.mode_desktop',
  mobile: 'tryhackx-avatars.mode_mobile',
};

const MODES = ['show', 'with_image', 'always', 'hide'];

export default class AvatarSettings extends Component {
  oninit(vnode) {
    super.oninit(vnode);
    this.saving = { desktop: false, mobile: false };
    this.error = null;
  }

  // Is Thumb Sliders installed AND enabled (both as an extension and via its
  // own on/off setting)? Used only to tailor the hints — the forum-side logic
  // self-detects thumbs via actual thumb presence regardless of this.
  thumbsActive() {
    let enabledExts = [];
    try {
      enabledExts = JSON.parse(app.data.settings.extensions_enabled || '[]');
    } catch (e) {
      enabledExts = [];
    }
    if (enabledExts.indexOf('tryhackx-thumb-sliders') === -1) return false;

    const v = app.data.settings['tryhackx-thumb-sliders.enabled'];
    // Default is enabled; treat missing / truthy as enabled.
    return v === undefined || (v !== false && v !== '0' && v !== 0 && v !== 'false');
  }

  current(device) {
    const v = app.data.settings[MODE_KEYS[device]];
    return MODES.indexOf(v) !== -1 ? v : 'show';
  }

  view() {
    const thumbs = this.thumbsActive();

    return (
      <div className="AvatarSettings Form-group">
        <label>{app.translator.trans('tryhackx-avatars.admin.title')}</label>
        <div className="helpText">{app.translator.trans('tryhackx-avatars.admin.help')}</div>

        {!thumbs && (
          <div className="AvatarSettings-notice helpText">
            <i className="fas fa-info-circle" />{' '}
            {app.translator.trans('tryhackx-avatars.admin.thumbs_inactive')}
          </div>
        )}

        {this.error && <div className="AvatarSettings-error">{this.error}</div>}

        <div className="AvatarSettings-devices">
          {this.renderDevice('desktop', 'fas fa-desktop', thumbs)}
          {this.renderDevice('mobile', 'fas fa-mobile-alt', thumbs)}
        </div>
      </div>
    );
  }

  renderDevice(device, icon, thumbs) {
    const value = this.current(device);

    const options = {};
    MODES.forEach((mode) => {
      options[mode] = app.translator.trans('tryhackx-avatars.admin.mode_' + mode);
    });

    return (
      <div className={'AvatarSettings-device AvatarSettings-device--' + device}>
        <div className="AvatarSettings-deviceHeader">
          <i className={icon + ' AvatarSettings-deviceIcon'} />
          <span>{app.translator.trans('tryhackx-avatars.admin.device_' + device)}</span>
          {this.saving[device] && <LoadingIndicator display="inline" size="small" />}
        </div>

        <Select
          value={value}
          options={options}
          disabled={this.saving[device]}
          onchange={(v) => this.save(device, v)}
        />

        <div className="AvatarSettings-deviceHint helpText">{this.hint(value, thumbs)}</div>
      </div>
    );
  }

  hint(value, thumbs) {
    if (value === 'show') return app.translator.trans('tryhackx-avatars.admin.hint_show');
    if (value === 'hide') return app.translator.trans('tryhackx-avatars.admin.hint_hide');
    // Replace modes:
    if (!thumbs) return app.translator.trans('tryhackx-avatars.admin.hint_replace_no_thumbs');
    if (value === 'with_image') return app.translator.trans('tryhackx-avatars.admin.hint_with_image');
    return app.translator.trans('tryhackx-avatars.admin.hint_always');
  }

  save(device, value) {
    if (this.saving[device]) return;
    this.saving[device] = true;
    this.error = null;
    m.redraw();

    app
      .request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/settings',
        body: { [MODE_KEYS[device]]: value },
      })
      .then(() => {
        // Persisted — mirror into the shared in-memory store so the other
        // extension's settings page (and a fresh visit here) see the new value.
        app.data.settings[MODE_KEYS[device]] = value;
        this.saving[device] = false;
        m.redraw();
      })
      .catch((err) => {
        this.error =
          (err && err.alert && err.alert.content) ||
          app.translator.trans('tryhackx-avatars.admin.save_error');
        this.saving[device] = false;
        m.redraw();
      });
  }
}
