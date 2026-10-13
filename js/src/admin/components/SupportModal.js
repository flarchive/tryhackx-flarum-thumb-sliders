import app from 'flarum/admin/app';
import Modal from 'flarum/common/components/Modal';

const WALLETS = [
  {
    name: 'Monero (XMR)',
    icon: 'fab fa-monero',
    address: '45hvee4Jv7qeAm6SrBzXb9YVjb8DkHtFtFh7qkDMxS9zYX3NRi1dV27MtSdVC5X8T1YVoiG8XFiJkh4p9UncqWGxHi4tiwk',
    color: '#ff6600',
  },
  {
    name: 'Bitcoin (BTC)',
    icon: 'fab fa-bitcoin',
    address: 'bc1qncavcek4kknpvykedxas8kxash9kdng990qed2',
    color: '#f7931a',
  },
  {
    name: 'Ethereum (ETH)',
    icon: 'fab fa-ethereum',
    address: '0xa3d38d5Cf202598dd782C611e9F43f342C967cF5',
    color: '#627eea',
  },
];

export default class SupportModal extends Modal {
  className() {
    return 'SupportModal Modal--small';
  }

  title() {
    return [
      <i className="fas fa-heart" style="color:#e74c3c;margin-right:8px" />,
      app.translator.trans('tryhackx-thumb-sliders.admin.support.title'),
    ];
  }

  content() {
    return (
      <div className="Modal-body">
        <p className="SupportModal-description">
          {app.translator.trans('tryhackx-thumb-sliders.admin.support.description')}
        </p>

        <div className="SupportModal-wallets">
          {WALLETS.map((wallet) => (
            <div className="SupportModal-wallet" key={wallet.name}>
              <div className="SupportModal-walletHeader">
                <i className={wallet.icon} style={'color:' + wallet.color} />
                <span>{wallet.name}</span>
              </div>
              <div className="SupportModal-walletAddress">
                <code>{wallet.address}</code>
                <button
                  className="Button Button--icon SupportModal-copyBtn"
                  title={app.translator.trans('tryhackx-thumb-sliders.admin.support.copy')}
                  onclick={(e) => this.copyAddress(e, wallet.address)}
                >
                  <i className="fas fa-copy" />
                </button>
              </div>
            </div>
          ))}
        </div>

        <p className="SupportModal-thanks">
          {app.translator.trans('tryhackx-thumb-sliders.admin.support.thanks')}
        </p>
      </div>
    );
  }

  copyAddress(e, address) {
    const btn = e.currentTarget;

    const showCopied = () => {
      const icon = btn.querySelector('i');
      if (icon) icon.className = 'fas fa-check';
      btn.classList.add('SupportModal-copyBtn--copied');
      setTimeout(() => {
        const ic = btn.querySelector('i');
        if (ic) ic.className = 'fas fa-copy';
        btn.classList.remove('SupportModal-copyBtn--copied');
      }, 2000);
    };

    const showFailed = () => {
      const icon = btn.querySelector('i');
      if (icon) icon.className = 'fas fa-times';
      setTimeout(() => {
        const ic = btn.querySelector('i');
        if (ic) ic.className = 'fas fa-copy';
      }, 2000);
    };

    // navigator.clipboard only exists in a secure context; on a plain-HTTP admin
    // panel (or when the browser blocks clipboard access) writeText() rejects.
    // Fall back to the legacy execCommand path and only show the failure icon if
    // that fails too — previously the rejection was swallowed and the button
    // gave the admin no feedback at all that the copy had not happened.
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(address).then(showCopied).catch(() => {
        if (this.legacyCopy(address)) showCopied();
        else showFailed();
      });
    } else if (this.legacyCopy(address)) {
      showCopied();
    } else {
      showFailed();
    }
  }

  legacyCopy(text) {
    try {
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.top = '-1000px';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      const ok = document.execCommand('copy');
      document.body.removeChild(ta);
      return ok;
    } catch (e) {
      return false;
    }
  }
}
