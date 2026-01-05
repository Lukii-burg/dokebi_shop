(function() {
  const style = document.createElement('style');
  style.textContent = `
  #flashModalOverlay {
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,0.65);
    backdrop-filter: blur(6px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 20px;
  }
  #flashModalOverlay.active { display: flex; }
  .flash-modal {
    background: linear-gradient(180deg, #0f172a, #0b1426);
    color: #e2e8f0;
    border-radius: 18px;
    padding: 24px 24px 18px;
    width: min(520px, 92vw);
    box-shadow: 0 30px 80px rgba(0,0,0,0.45);
    border: 1px solid rgba(255,255,255,0.08);
    position: relative;
    font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  }
  .flash-modal__close {
    position: absolute;
    top: 12px; right: 12px;
    background: transparent;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    font-size: 18px;
  }
  .flash-modal__icon {
    width: 64px; height: 64px;
    border-radius: 50%;
    background: rgba(34,197,94,0.16);
    border: 1px solid rgba(34,197,94,0.4);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #22c55e;
    font-size: 30px;
    margin-bottom: 10px;
  }
  .flash-modal__title {
    margin: 0 0 4px;
    font-size: 20px;
    font-weight: 800;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
  }
  .flash-modal__subtitle {
    margin: 0 0 12px;
    color: #cbd5e1;
    font-size: 14px;
  }
  .flash-modal__body {
    margin: 0 0 14px;
    line-height: 1.55;
    color: #cbd5e1;
    font-size: 14px;
  }
  .flash-modal__body strong { color: #e2e8f0; }
  .flash-modal__detail {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    padding: 12px;
    margin-top: 8px;
    display: grid;
    gap: 6px;
  }
  .flash-modal__row {
    display:flex;
    justify-content: space-between;
    gap: 8px;
    font-size: 14px;
  }
  .flash-modal__row span:first-child { color:#94a3b8; }
  .flash-modal__row span:last-child { color:#e2e8f0; font-weight:600; text-align:right; }
  .flash-modal__footer {
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
  }
  .flash-modal__btn, .flash-modal__btn-secondary {
    border: none;
    border-radius: 12px;
    padding: 11px 16px;
    cursor: pointer;
    font-weight: 700;
    box-shadow: 0 12px 34px rgba(34,197,94,0.35);
  }
  .flash-modal__btn {
    background: linear-gradient(90deg,#22c55e,#16a34a);
    color: #fff;
  }
  .flash-modal__btn-secondary {
    background: rgba(255,255,255,0.08);
    color: #e2e8f0;
    border: 1px solid rgba(255,255,255,0.1);
  }
  @media (max-width: 520px) {
    .flash-modal { width: 100%; }
  }
  `;
  document.head.appendChild(style);

  function ensureModal() {
    let overlay = document.getElementById('flashModalOverlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'flashModalOverlay';
      overlay.innerHTML = `
        <div class="flash-modal" role="alertdialog" aria-modal="true">
          <button class="flash-modal__close" aria-label="Close">&times;</button>
          <div class="flash-modal__icon">✔</div>
          <h3 class="flash-modal__title"><span>Payment Complete</span></h3>
          <div class="flash-modal__subtitle"></div>
          <div class="flash-modal__body"></div>
          <div class="flash-modal__detail"></div>
          <div class="flash-modal__footer">
            <button class="flash-modal__btn-secondary" type="button"></button>
            <button class="flash-modal__btn" type="button">Done</button>
          </div>
        </div>
      `;
      document.body.appendChild(overlay);
      const close = overlay.querySelector('.flash-modal__close');
      overlay.hideModal = () => overlay.classList.remove('active');
      close.addEventListener('click', () => overlay.hideModal());
      overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.hideModal(); });
    }
    return overlay;
  }

  // opts: { title, subtitle, details: [{label,value}], icon, ordersLink, primaryText, secondaryText, primaryLink, secondaryLink }
  window.showFlashModal = function(message, opts = {}) {
    if (!message) return;
    const overlay = ensureModal();
    const body = overlay.querySelector('.flash-modal__body');
    const title = overlay.querySelector('.flash-modal__title');
    const subtitle = overlay.querySelector('.flash-modal__subtitle');
    const detailWrap = overlay.querySelector('.flash-modal__detail');
    const icon = overlay.querySelector('.flash-modal__icon');
    const primaryBtn = overlay.querySelector('.flash-modal__btn');
    const secondaryBtn = overlay.querySelector('.flash-modal__btn-secondary');
    const hide = overlay.hideModal || (() => overlay.classList.remove('active'));

    title.textContent = opts.title || 'Payment Complete';
    subtitle.textContent = opts.subtitle || '';
    subtitle.style.display = subtitle.textContent ? 'block' : 'none';
    if (opts.icon) icon.textContent = opts.icon;

    const lines = Array.isArray(message) ? message : String(message).split(/\r?\n/);
    body.innerHTML = lines.map(l => l.trim()).filter(Boolean).map(l => `<div>${l.replace(/</g,'&lt;')}</div>`).join('');

    const details = Array.isArray(opts.details) ? opts.details : [];
    if (details.length === 0) {
      detailWrap.style.display = 'none';
    } else {
      detailWrap.style.display = 'grid';
      detailWrap.innerHTML = details.map(d => `
        <div class="flash-modal__row">
          <span>${(d.label || '').replace(/</g,'&lt;')}</span>
          <span>${(d.value || '').toString().replace(/</g,'&lt;')}</span>
        </div>
      `).join('');
    }

    const msgString = Array.isArray(message) ? message.join(' ') : String(message);
    const lowerMsg = msgString.toLowerCase();

    const hasPrimaryText = Object.prototype.hasOwnProperty.call(opts, 'primaryText');
    const hasSecondaryText = Object.prototype.hasOwnProperty.call(opts, 'secondaryText');
    const hasPrimaryLink = Object.prototype.hasOwnProperty.call(opts, 'primaryLink');
    const hasSecondaryLink = Object.prototype.hasOwnProperty.call(opts, 'secondaryLink');

    let primaryText = hasPrimaryText ? opts.primaryText : 'Done';
    let secondaryText = hasSecondaryText ? opts.secondaryText : (opts.ordersLink ? 'View Orders' : 'Close');
    let primaryLink = hasPrimaryLink ? opts.primaryLink : '';
    let secondaryLink = hasSecondaryLink ? opts.secondaryLink : (opts.ordersLink ?? window.flashModalOrdersLink ?? '/user/account.php');

    // Auto-tune buttons for cart success when no explicit label/link provided.
    if (lowerMsg.includes('added to cart')) {
      if (!hasSecondaryText) secondaryText = 'Go to cart';
      if (!hasSecondaryLink) secondaryLink = '/user/cart.php';
    }
    // Auto-tune buttons for wishlist success when no explicit label/link provided.
    if (lowerMsg.includes('wishlist')) {
      if (!hasSecondaryText) secondaryText = 'Go to wishlist';
      if (!hasSecondaryLink) secondaryLink = '/user/account.php?panel=wishlist';
    }

    if (primaryBtn) primaryBtn.textContent = primaryText;
    if (secondaryBtn) secondaryBtn.textContent = secondaryText;

    if (primaryBtn) {
      primaryBtn.onclick = () => {
        if (primaryLink) {
          window.location.href = primaryLink;
        } else {
          hide();
        }
      };
    }

    if (secondaryBtn) {
      secondaryBtn.onclick = () => {
        if (secondaryLink) {
          window.location.href = secondaryLink;
        } else {
          hide();
        }
      };
    }

    window.flashModalOrdersLink = secondaryLink || window.flashModalOrdersLink || '/user/account.php';

    overlay.classList.add('active');
    setTimeout(() => {
      const btn = overlay.querySelector('.flash-modal__btn');
      if (btn) btn.focus();
    }, 60);
  };
})();
