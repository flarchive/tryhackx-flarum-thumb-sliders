import app from 'flarum/admin/app';
import SupportModal from './components/SupportModal';
import FallbackImageManager from './components/FallbackImageManager';

app.initializers.add('tryhackx-thumb-sliders-support', () => {
  app.extensionData.for('tryhackx-thumb-sliders').registerSetting(function () {
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
  app.extensionData
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
    });
});
