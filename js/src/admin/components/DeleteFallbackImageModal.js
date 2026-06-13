import app from 'flarum/admin/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';

/**
 * Confirmation modal for deleting a fallback image. Replaces the native
 * window.confirm() prompt so the dialog matches Flarum's UI and can show a
 * preview of the image being removed.
 *
 * Attrs:
 *   - file:     the file object { filename, url } being deleted (for the preview)
 *   - ondelete: () => Promise  the actual delete request; the modal drives the
 *               loading state and closes on success / shows the error on failure.
 */
export default class DeleteFallbackImageModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);
    this.loading = false;
    this.error = null;
  }

  className() {
    return 'DeleteFallbackImageModal Modal--small';
  }

  title() {
    return app.translator.trans('tryhackx-thumb-sliders.admin.fallback.delete_modal_title');
  }

  content() {
    const file = this.attrs.file || {};

    return (
      <div className="Modal-body">
        <div className="DeleteFallbackImageModal-content">
          {file.url && (
            <div className="DeleteFallbackImageModal-preview">
              <img src={file.url} alt="" />
            </div>
          )}

          <p className="DeleteFallbackImageModal-message">
            {app.translator.trans('tryhackx-thumb-sliders.admin.fallback.confirm_delete')}
          </p>

          {file.filename && (
            <p className="DeleteFallbackImageModal-filename">
              <code>{file.filename}</code>
            </p>
          )}

          {this.error && <div className="DeleteFallbackImageModal-error">{this.error}</div>}

          <div className="DeleteFallbackImageModal-buttons">
            <Button
              className="Button Button--inverted"
              onclick={() => this.hide()}
              disabled={this.loading}
            >
              {app.translator.trans('tryhackx-thumb-sliders.admin.fallback.delete_modal_cancel')}
            </Button>
            <Button
              className="Button Button--danger"
              onclick={() => this.confirm()}
              loading={this.loading}
              disabled={this.loading}
            >
              {app.translator.trans('tryhackx-thumb-sliders.admin.fallback.delete')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  confirm() {
    if (this.loading) return;
    this.loading = true;
    this.error = null;
    m.redraw();

    Promise.resolve(this.attrs.ondelete())
      .then(() => {
        this.hide();
      })
      .catch((err) => {
        this.loading = false;
        this.error =
          (err && err.response && err.response.error) ||
          (err && err.alert && err.alert.content) ||
          app.translator.trans('tryhackx-thumb-sliders.admin.fallback.delete_modal_error');
        m.redraw();
      });
  }
}
