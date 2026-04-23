import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';

/**
 * FallbackImageManager - Admin gallery component for managing fallback images.
 *
 * Allows the admin to upload images, browse uploaded files, mark one as the
 * active fallback image, and delete unused entries. Active selection is stored
 * in the `tryhackx-thumb-sliders.fallback_image` setting.
 */
export default class FallbackImageManager extends Component {
  oninit(vnode) {
    super.oninit(vnode);
    this.files = [];
    this.loading = true;
    this.uploading = false;
    this.error = null;

    this.loadFiles();
  }

  view() {
    const mode = app.data.settings['tryhackx-thumb-sliders.fallback_mode'] || 'none';
    const activeFile = app.data.settings['tryhackx-thumb-sliders.fallback_image'] || '';

    return (
      <div className="ThumbSlidersFallbackManager Form-group">
        <label>{app.translator.trans('tryhackx-thumb-sliders.admin.fallback.title')}</label>

        {mode !== 'custom' && (
          <div className="helpText ThumbSlidersFallbackManager__notice">
            {app.translator.trans('tryhackx-thumb-sliders.admin.fallback.not_active_mode')}
          </div>
        )}

        <div className="ThumbSlidersFallbackManager__upload">
          <label className="Button">
            <i className="fas fa-upload Button-icon" />
            <span>{app.translator.trans('tryhackx-thumb-sliders.admin.fallback.upload_button')}</span>
            <input
              type="file"
              accept="image/webp,image/jpeg,image/png,image/gif,image/bmp,image/avif,image/svg+xml"
              disabled={this.uploading}
              onchange={(e) => this.uploadFile(e)}
              style="display: none;"
            />
          </label>
          {this.uploading && (
            <span className="ThumbSlidersFallbackManager__status">
              {app.translator.trans('tryhackx-thumb-sliders.admin.fallback.uploading')}
            </span>
          )}
        </div>

        {this.error && (
          <div className="ThumbSlidersFallbackManager__error">{this.error}</div>
        )}

        {this.loading ? (
          <LoadingIndicator />
        ) : this.files.length === 0 ? (
          <div className="helpText">
            {app.translator.trans('tryhackx-thumb-sliders.admin.fallback.no_files')}
          </div>
        ) : (
          <div className="ThumbSlidersFallbackManager__grid">
            {this.files.map((file) => {
              const isActive = file.filename === activeFile;
              return (
                <div
                  key={file.filename}
                  className={'ThumbSlidersFallbackManager__item' + (isActive ? ' ThumbSlidersFallbackManager__item--active' : '')}
                  onclick={() => this.selectFile(file.filename)}
                  title={file.filename}
                >
                  <img src={file.url} alt="" loading="lazy" />
                  {isActive && (
                    <span className="ThumbSlidersFallbackManager__item-active">
                      <i className="fas fa-check" />
                    </span>
                  )}
                  <button
                    type="button"
                    className="ThumbSlidersFallbackManager__item-delete"
                    onclick={(e) => { e.stopPropagation(); this.deleteFile(file.filename); }}
                    title={app.translator.trans('tryhackx-thumb-sliders.admin.fallback.delete')}
                  >
                    <i className="fas fa-times" />
                  </button>
                </div>
              );
            })}
          </div>
        )}

        {activeFile && (
          <div className="ThumbSlidersFallbackManager__clear">
            <button
              type="button"
              className="Button Button--text"
              onclick={() => this.selectFile('')}
            >
              {app.translator.trans('tryhackx-thumb-sliders.admin.fallback.clear_selection')}
            </button>
          </div>
        )}
      </div>
    );
  }

  loadFiles() {
    this.loading = true;
    app.request({
      method: 'GET',
      url: app.forum.attribute('apiUrl') + '/thumb-sliders/uploads',
    }).then((res) => {
      this.files = (res && res.data) || [];
      this.loading = false;
      m.redraw();
    }).catch((err) => {
      this.error = this.extractError(err);
      this.loading = false;
      m.redraw();
    });
  }

  uploadFile(e) {
    const input = e.target;
    const file = input.files && input.files[0];
    if (!file) return;

    this.uploading = true;
    this.error = null;
    m.redraw();

    const formData = new FormData();
    formData.append('file', file);

    app.request({
      method: 'POST',
      url: app.forum.attribute('apiUrl') + '/thumb-sliders/uploads',
      serialize: (raw) => raw,
      body: formData,
    }).then((res) => {
      if (res && res.filename) {
        this.files.unshift(res);
        // Auto-select newly uploaded file
        this.selectFile(res.filename);
      }
      this.uploading = false;
      input.value = '';
      m.redraw();
    }).catch((err) => {
      this.error = this.extractError(err);
      this.uploading = false;
      input.value = '';
      m.redraw();
    });
  }

  deleteFile(filename) {
    if (!confirm(app.translator.trans('tryhackx-thumb-sliders.admin.fallback.confirm_delete'))) return;

    app.request({
      method: 'DELETE',
      url: app.forum.attribute('apiUrl') + '/thumb-sliders/uploads/' + encodeURIComponent(filename),
    }).then(() => {
      this.files = this.files.filter((f) => f.filename !== filename);
      // Server clears the setting if the deleted file was active – mirror that locally
      if (app.data.settings['tryhackx-thumb-sliders.fallback_image'] === filename) {
        app.data.settings['tryhackx-thumb-sliders.fallback_image'] = '';
      }
      m.redraw();
    }).catch((err) => {
      this.error = this.extractError(err);
      m.redraw();
    });
  }

  selectFile(filename) {
    this.error = null;
    app.request({
      method: 'POST',
      url: app.forum.attribute('apiUrl') + '/settings',
      body: {
        'tryhackx-thumb-sliders.fallback_image': filename,
      },
    }).then(() => {
      app.data.settings['tryhackx-thumb-sliders.fallback_image'] = filename;
      m.redraw();
    }).catch((err) => {
      this.error = this.extractError(err);
      m.redraw();
    });
  }

  extractError(err) {
    if (!err) return 'Request failed.';
    try {
      if (err.response && err.response.errors && err.response.errors.length) {
        return err.response.errors.map((e) => e.detail || e.title || '').filter(Boolean).join(' ');
      }
      if (err.response && err.response.error) {
        return err.response.error;
      }
      if (err.alert && err.alert.content) return err.alert.content;
    } catch (_) { /* ignore */ }
    return err.message || 'Request failed.';
  }
}
