import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import ResetExtensionSettingsModal from 'flarum/admin/components/ResetExtensionSettingsModal';
import SupportModal from './components/SupportModal';
import FallbackImageManager from './components/FallbackImageManager';
import AvatarSettings from './components/AvatarSettings';

// Give the Cancel button in core's "Reset extension settings" modal Flarum's
// standard `Button--inverted` styling (core renders it as a plain borderless
// button). The modal is registered in the admin component registry, so we
// extend it directly instead of running a whole-document MutationObserver for
// the entire admin session. Guarded in case a future core build drops it.
app.initializers.add('tryhackx-thumb-sliders-cancel-inverted', () => {
  if (!ResetExtensionSettingsModal || !ResetExtensionSettingsModal.prototype) return;

  const invertCancel = function () {
    const root = this.element;
    if (!root) return;
    const cancel = root.querySelector('.Form-controls .Button:not(.Button--danger):not(.Button--primary)');
    if (cancel) cancel.classList.add('Button--inverted');
  };

  extend(ResetExtensionSettingsModal.prototype, 'oncreate', invertCancel);
  extend(ResetExtensionSettingsModal.prototype, 'onupdate', invertCancel);
});

app.initializers.add('tryhackx-thumb-sliders-support', () => {
  app.registry.for('tryhackx-thumb-sliders').registerSetting(function () {
    return m('div', { className: 'ThumbSliders-support' }, [
      m('button', {
        className: 'Button',
        onclick: () => app.modal.show(SupportModal),
      }, [
        m('i', { className: 'fas fa-heart Button-icon icon' }),
        app.translator.trans('tryhackx-thumb-sliders.admin.support.button'),
      ]),
    ]);
  });
});

app.initializers.add('tryhackx-thumb-sliders', () => {
  app.registry
    .for('tryhackx-thumb-sliders')
    .registerSetting({
      setting: 'tryhackx-thumb-sliders.enabled',
      type: 'bool',
      label: app.translator.trans('tryhackx-thumb-sliders.admin.settings.enabled_label'),
      help: app.translator.trans('tryhackx-thumb-sliders.admin.settings.enabled_help'),
    })
    .registerSetting({
      setting: 'tryhackx-thumb-sliders.slider_width',
      type: 'number',
      label: app.translator.trans('tryhackx-thumb-sliders.admin.settings.slider_width_label'),
      help: app.translator.trans('tryhackx-thumb-sliders.admin.settings.slider_width_help'),
      min: 50,
      max: 400,
    })
    .registerSetting({
      setting: 'tryhackx-thumb-sliders.autoplay_speed',
      type: 'number',
      label: app.translator.trans('tryhackx-thumb-sliders.admin.settings.autoplay_speed_label'),
      help: app.translator.trans('tryhackx-thumb-sliders.admin.settings.autoplay_speed_help'),
      min: 500,
      max: 10000,
    })
    .registerSetting({
      setting: 'tryhackx-thumb-sliders.max_images',
      type: 'number',
      label: app.translator.trans('tryhackx-thumb-sliders.admin.settings.max_images_label'),
      help: app.translator.trans('tryhackx-thumb-sliders.admin.settings.max_images_help'),
      min: 1,
      max: 20,
    })
    .registerSetting({
      setting: 'tryhackx-thumb-sliders.min_img_size',
      type: 'number',
      label: app.translator.trans('tryhackx-thumb-sliders.admin.settings.min_img_size_label'),
      help: app.translator.trans('tryhackx-thumb-sliders.admin.settings.min_img_size_help'),
      min: 0,
      max: 10000,
    })
    .registerSetting({
      setting: 'tryhackx-thumb-sliders.max_img_size',
      type: 'number',
      label: app.translator.trans('tryhackx-thumb-sliders.admin.settings.max_img_size_label'),
      help: app.translator.trans('tryhackx-thumb-sliders.admin.settings.max_img_size_help'),
      min: 0,
      max: 10000,
    })
    .registerSetting({
      setting: 'tryhackx-thumb-sliders.fallback_mode',
      type: 'select',
      options: {
        'none': app.translator.trans('tryhackx-thumb-sliders.admin.settings.fallback_mode_none'),
        'default': app.translator.trans('tryhackx-thumb-sliders.admin.settings.fallback_mode_default'),
        'custom': app.translator.trans('tryhackx-thumb-sliders.admin.settings.fallback_mode_custom'),
      },
      label: app.translator.trans('tryhackx-thumb-sliders.admin.settings.fallback_mode_label'),
      help: app.translator.trans('tryhackx-thumb-sliders.admin.settings.fallback_mode_help'),
    })
    .registerSetting(function () {
      return m(FallbackImageManager);
    })
    // Shared avatar section (also present in Topic Rating; same setting keys).
    .registerSetting(function () {
      return m(AvatarSettings);
    });
});
