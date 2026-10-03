/**
 * QR Code Creation and Customisation Controller
 */
const QrController = (() => {
  let isEditMode = false;
  let qrId = null;
  let currentType = 'url';
  let uploadedLogoPath = null;
  let previewTimeout = null;

  async function init(editMode = false) {
    isEditMode = editMode;
    await Auth.requireAuth();

    setupTypeSelector();
    setupColorPickers();
    setupLivePreviewListeners();
    setupLogoUpload();

    if (isEditMode) {
      const urlParams = new URLSearchParams(window.location.search);
      qrId = urlParams.get('id');
      if (!qrId) {
        API.showToast('No QR Code ID provided', 'error');
        window.location.href = 'dashboard.html';
        return;
      }
      await loadQrData(qrId);
    } else {
      updateTypeFields('url');
      triggerPreview();
    }

    setupFormSubmit();
  }

  function setupTypeSelector() {
    const options = document.querySelectorAll('.type-option');
    options.forEach(opt => {
      opt.addEventListener('click', () => {
        options.forEach(o => o.classList.remove('active'));
        opt.classList.add('active');
        const type = opt.getAttribute('data-type');
        currentType = type;
        updateTypeFields(type);
        triggerPreview();
      });
    });
  }

  function updateTypeFields(type) {
    const container = document.getElementById('type-fields-container');
    if (!container) return;

    let html = '';

    switch (type) {
      case 'url':
        html = `
          <div class="form-group">
            <label class="form-label">Website / Target URL *</label>
            <input type="url" id="field-url" class="form-control" placeholder="https://yourwebsite.com/promo" required>
            <p class="form-text">Supports secure http:// and https:// URLs. You can modify this destination anytime without reprinting your QR code.</p>
          </div>
        `;
        break;

      case 'text':
        html = `
          <div class="form-group">
            <label class="form-label">Plain Text Content *</label>
            <textarea id="field-text" class="form-control" rows="4" placeholder="Enter your text, instructions, or message here..." required></textarea>
          </div>
        `;
        break;

      case 'wifi':
        html = `
          <div class="form-group">
            <label class="form-label">Network Name (SSID) *</label>
            <input type="text" id="field-wifi-ssid" class="form-control" placeholder="Office-Guest-WiFi" required>
          </div>
          <div class="form-group">
            <label class="form-label">Encryption Type</label>
            <select id="field-wifi-type" class="form-control">
              <option value="WPA">WPA / WPA2 / WPA3 (Recommended)</option>
              <option value="WEP">WEP</option>
              <option value="nopass">No Password (Open)</option>
            </select>
          </div>
          <div class="form-group" id="wifi-pass-group">
            <label class="form-label">Password</label>
            <input type="password" id="field-wifi-pass" class="form-control" placeholder="WiFi Password">
          </div>
        `;
        break;

      case 'vcard':
        html = `
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
              <label class="form-label">First Name *</label>
              <input type="text" id="field-vcard-first" class="form-control" placeholder="John" required>
            </div>
            <div class="form-group">
              <label class="form-label">Last Name *</label>
              <input type="text" id="field-vcard-last" class="form-control" placeholder="Doe" required>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Company / Organization</label>
            <input type="text" id="field-vcard-company" class="form-control" placeholder="Acme Corp">
          </div>
          <div class="form-group">
            <label class="form-label">Job Title</label>
            <input type="text" id="field-vcard-title" class="form-control" placeholder="Head of Growth">
          </div>
          <div class="form-group">
            <label class="form-label">Phone Number</label>
            <input type="tel" id="field-vcard-phone" class="form-control" placeholder="+1 (555) 000-1122">
          </div>
          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" id="field-vcard-email" class="form-control" placeholder="john@example.com">
          </div>
          <div class="form-group">
            <label class="form-label">Website</label>
            <input type="url" id="field-vcard-website" class="form-control" placeholder="https://example.com">
          </div>
        `;
        break;

      case 'email':
        html = `
          <div class="form-group">
            <label class="form-label">Recipient Email *</label>
            <input type="email" id="field-email-to" class="form-control" placeholder="contact@business.com" required>
          </div>
          <div class="form-group">
            <label class="form-label">Subject</label>
            <input type="text" id="field-email-sub" class="form-control" placeholder="Inquiry from QR Scan">
          </div>
          <div class="form-group">
            <label class="form-label">Message Body</label>
            <textarea id="field-email-body" class="form-control" rows="3" placeholder="Hello, I'd like more information about..."></textarea>
          </div>
        `;
        break;

      case 'phone':
        html = `
          <div class="form-group">
            <label class="form-label">Phone Number *</label>
            <input type="tel" id="field-phone" class="form-control" placeholder="+1234567890" required>
            <p class="form-text">Include country code for best compatibility (e.g. +1...)</p>
          </div>
        `;
        break;

      case 'sms':
        html = `
          <div class="form-group">
            <label class="form-label">Recipient Number *</label>
            <input type="tel" id="field-sms-phone" class="form-control" placeholder="+1234567890" required>
          </div>
          <div class="form-group">
            <label class="form-label">Pre-filled Message</label>
            <textarea id="field-sms-msg" class="form-control" rows="3" placeholder="Send this code to claim your discount..."></textarea>
          </div>
        `;
        break;

      case 'social':
        html = `
          <div class="form-group">
            <label class="form-label">Social Platform</label>
            <select id="field-social-platform" class="form-control">
              <option value="twitter">X / Twitter</option>
              <option value="instagram">Instagram</option>
              <option value="facebook">Facebook</option>
              <option value="linkedin">LinkedIn</option>
              <option value="youtube">YouTube</option>
              <option value="tiktok">TikTok</option>
              <option value="github">GitHub</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Username / Handle *</label>
            <input type="text" id="field-social-handle" class="form-control" placeholder="@username" required>
          </div>
        `;
        break;

      default:
        html = `
          <div class="form-group">
            <label class="form-label">Destination URL or Content *</label>
            <input type="text" id="field-url" class="form-control" placeholder="https://example.com" required>
          </div>
        `;
    }

    container.innerHTML = html;

    // Attach listener to new inputs for live preview
    container.querySelectorAll('input, textarea, select').forEach(input => {
      input.addEventListener('input', triggerPreview);
      input.addEventListener('change', triggerPreview);
    });
  }

  function setupColorPickers() {
    const fgColor = document.getElementById('config-fg-color');
    const fgHex = document.getElementById('config-fg-hex');
    const bgColor = document.getElementById('config-bg-color');
    const bgHex = document.getElementById('config-bg-hex');

    if (fgColor && fgHex) {
      fgColor.addEventListener('input', (e) => {
        fgHex.value = e.target.value;
        triggerPreview();
      });
      fgHex.addEventListener('input', (e) => {
        if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) {
          fgColor.value = e.target.value;
          triggerPreview();
        }
      });
    }

    if (bgColor && bgHex) {
      bgColor.addEventListener('input', (e) => {
        bgHex.value = e.target.value;
        triggerPreview();
      });
      bgHex.addEventListener('input', (e) => {
        if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) {
          bgColor.value = e.target.value;
          triggerPreview();
        }
      });
    }
  }

  function setupLivePreviewListeners() {
    const inputs = ['config-size', 'config-margin', 'config-ecc', 'config-style'];
    inputs.forEach(id => {
      const el = document.getElementById(id);
      if (el) {
        el.addEventListener('input', triggerPreview);
        el.addEventListener('change', triggerPreview);
      }
    });

    const sizeRange = document.getElementById('config-size');
    const sizeDisplay = document.getElementById('size-display');
    if (sizeRange && sizeDisplay) {
      sizeRange.addEventListener('input', (e) => {
        sizeDisplay.textContent = `${e.target.value}px`;
      });
    }
  }

  function setupLogoUpload() {
    const logoInput = document.getElementById('logo-file-input');
    const logoRemoveBtn = document.getElementById('btn-remove-logo');
    const logoStatus = document.getElementById('logo-status');

    if (logoInput) {
      logoInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('logo', file);

        logoStatus.textContent = 'Uploading logo...';

        try {
          const res = await API.post('/qr/upload-logo.php', formData);
          uploadedLogoPath = res.data.logo_path;
          logoStatus.textContent = `Attached: ${file.name}`;
          if (logoRemoveBtn) logoRemoveBtn.style.display = 'inline-block';
          triggerPreview();
          API.showToast('Logo attached to QR design.', 'success');
        } catch (err) {
          logoStatus.textContent = '';
          API.showToast(err.message || 'Logo upload failed', 'error');
        }
      });
    }

    if (logoRemoveBtn) {
      logoRemoveBtn.addEventListener('click', () => {
        uploadedLogoPath = null;
        if (logoInput) logoInput.value = '';
        if (logoStatus) logoStatus.textContent = '';
        logoRemoveBtn.style.display = 'none';
        triggerPreview();
      });
    }
  }

  function triggerPreview() {
    clearTimeout(previewTimeout);
    previewTimeout = setTimeout(fetchLivePreview, 250);
  }

  async function fetchLivePreview() {
    const previewBox = document.getElementById('live-preview-box');
    if (!previewBox) return;

    const payload = extractFormData();
    const config = payload.config;

    try {
      const res = await API.get('/qr/preview.php', {
        type: payload.type,
        destination: payload.destination || 'https://example.com',
        type_data: JSON.stringify(payload.type_data || {}),
        config: JSON.stringify(config),
        format: 'svg'
      });

      if (res.data?.svg) {
        previewBox.innerHTML = res.data.svg;
      }
    } catch (e) {
      console.warn('Preview error:', e);
    }
  }

  function extractFormData() {
    const name = document.getElementById('qr-name')?.value?.trim() || 'My QR Code';
    const expiresAt = document.getElementById('qr-expires')?.value || null;

    let destination = 'https://example.com';
    let typeData = {};

    switch (currentType) {
      case 'url':
        destination = document.getElementById('field-url')?.value?.trim() || 'https://example.com';
        typeData = { url: destination };
        break;

      case 'text':
        destination = document.getElementById('field-text')?.value?.trim() || 'Hello World';
        typeData = { text: destination };
        break;

      case 'wifi':
        const ssid = document.getElementById('field-wifi-ssid')?.value?.trim() || 'Network';
        const enc = document.getElementById('field-wifi-type')?.value || 'WPA';
        const pass = document.getElementById('field-wifi-pass')?.value || '';
        destination = `WIFI:S:${ssid};T:${enc};P:${pass};;`;
        typeData = { ssid, encryption: enc, password: pass };
        break;

      case 'vcard':
        const first = document.getElementById('field-vcard-first')?.value?.trim() || '';
        const last = document.getElementById('field-vcard-last')?.value?.trim() || '';
        const comp = document.getElementById('field-vcard-company')?.value?.trim() || '';
        const title = document.getElementById('field-vcard-title')?.value?.trim() || '';
        const phone = document.getElementById('field-vcard-phone')?.value?.trim() || '';
        const email = document.getElementById('field-vcard-email')?.value?.trim() || '';
        const web = document.getElementById('field-vcard-website')?.value?.trim() || '';
        destination = `BEGIN:VCARD\nVERSION:3.0\nN:${last};${first};;;\nFN:${first} ${last}\nORG:${comp}\nTEL:${phone}\nEMAIL:${email}\nURL:${web}\nEND:VCARD`;
        typeData = { first_name: first, last_name: last, company: comp, title, phone, email, website: web };
        break;

      case 'email':
        const to = document.getElementById('field-email-to')?.value?.trim() || '';
        const sub = document.getElementById('field-email-sub')?.value?.trim() || '';
        const body = document.getElementById('field-email-body')?.value?.trim() || '';
        destination = `mailto:${to}?subject=${encodeURIComponent(sub)}&body=${encodeURIComponent(body)}`;
        typeData = { email: to, subject: sub, body };
        break;

      case 'phone':
        const ph = document.getElementById('field-phone')?.value?.trim() || '';
        destination = `tel:${ph}`;
        typeData = { phone: ph };
        break;

      case 'sms':
        const sPh = document.getElementById('field-sms-phone')?.value?.trim() || '';
        const sMsg = document.getElementById('field-sms-msg')?.value?.trim() || '';
        destination = `smsto:${sPh}:${encodeURIComponent(sMsg)}`;
        typeData = { phone: sPh, message: sMsg };
        break;

      case 'social':
        const plat = document.getElementById('field-social-platform')?.value || 'twitter';
        const cleanHandle = (document.getElementById('field-social-handle')?.value?.trim() || '').replace('@', '');
        let socUrl = `https://${plat}.com/${cleanHandle}`;
        if (plat === 'youtube') socUrl = `https://youtube.com/@${cleanHandle}`;
        if (plat === 'linkedin') socUrl = `https://linkedin.com/in/${cleanHandle}`;
        if (plat === 'twitter') socUrl = `https://x.com/${cleanHandle}`;
        destination = socUrl;
        typeData = { platform: plat, handle: cleanHandle };
        break;
    }

    const fgColor = document.getElementById('config-fg-hex')?.value || '#0f172a';
    const bgColor = document.getElementById('config-bg-hex')?.value || '#ffffff';
    const size = parseInt(document.getElementById('config-size')?.value || 300, 10);
    const margin = parseInt(document.getElementById('config-margin')?.value || 2, 10);
    const ecc = document.getElementById('config-ecc')?.value || 'M';
    const style = document.getElementById('config-style')?.value || 'dots';

    const config = {
      fg_color: fgColor,
      bg_color: bgColor,
      size,
      margin,
      ecc,
      style,
      logo_path: uploadedLogoPath
    };

    return {
      name,
      type: currentType,
      destination,
      type_data: typeData,
      config,
      expires_at: expiresAt
    };
  }

  async function loadQrData(id) {
    try {
      const res = await API.get('/qr/get.php', { id });
      const qr = res.data;

      document.getElementById('qr-name').value = qr.name || '';
      if (qr.expires_at) {
        document.getElementById('qr-expires').value = qr.expires_at.replace(' ', 'T').substring(0, 16);
      }

      currentType = qr.type || 'url';
      document.querySelectorAll('.type-option').forEach(opt => {
        if (opt.getAttribute('data-type') === currentType) {
          opt.classList.add('active');
        } else {
          opt.classList.remove('active');
        }
      });

      updateTypeFields(currentType);

      // Populate type fields from type_data
      const td = qr.type_data || {};
      if (currentType === 'url') {
        const u = document.getElementById('field-url');
        if (u) u.value = td.url || qr.destination;
      } else if (currentType === 'text') {
        const t = document.getElementById('field-text');
        if (t) t.value = td.text || qr.destination;
      } else if (currentType === 'wifi') {
        if (document.getElementById('field-wifi-ssid')) document.getElementById('field-wifi-ssid').value = td.ssid || '';
        if (document.getElementById('field-wifi-type')) document.getElementById('field-wifi-type').value = td.encryption || 'WPA';
        if (document.getElementById('field-wifi-pass')) document.getElementById('field-wifi-pass').value = td.password || '';
      } else if (currentType === 'vcard') {
        if (document.getElementById('field-vcard-first')) document.getElementById('field-vcard-first').value = td.first_name || '';
        if (document.getElementById('field-vcard-last')) document.getElementById('field-vcard-last').value = td.last_name || '';
        if (document.getElementById('field-vcard-company')) document.getElementById('field-vcard-company').value = td.company || '';
        if (document.getElementById('field-vcard-title')) document.getElementById('field-vcard-title').value = td.title || '';
        if (document.getElementById('field-vcard-phone')) document.getElementById('field-vcard-phone').value = td.phone || '';
        if (document.getElementById('field-vcard-email')) document.getElementById('field-vcard-email').value = td.email || '';
        if (document.getElementById('field-vcard-website')) document.getElementById('field-vcard-website').value = td.website || '';
      } else if (currentType === 'social') {
        if (document.getElementById('field-social-platform')) document.getElementById('field-social-platform').value = td.platform || 'twitter';
        if (document.getElementById('field-social-handle')) document.getElementById('field-social-handle').value = td.handle || '';
      }

      // Populate config styling
      const cfg = qr.config || {};
      if (cfg.fg_color) {
        document.getElementById('config-fg-color').value = cfg.fg_color;
        document.getElementById('config-fg-hex').value = cfg.fg_color;
      }
      if (cfg.bg_color) {
        document.getElementById('config-bg-color').value = cfg.bg_color;
        document.getElementById('config-bg-hex').value = cfg.bg_color;
      }
      if (cfg.size) {
        document.getElementById('config-size').value = cfg.size;
        document.getElementById('size-display').textContent = `${cfg.size}px`;
      }
      if (cfg.margin !== undefined) {
        document.getElementById('config-margin').value = cfg.margin;
      }
      if (cfg.ecc) {
        document.getElementById('config-ecc').value = cfg.ecc;
      }
      if (cfg.style) {
        document.getElementById('config-style').value = cfg.style;
      }
      if (cfg.logo_path) {
        uploadedLogoPath = cfg.logo_path;
        document.getElementById('logo-status').textContent = 'Custom logo active';
        document.getElementById('btn-remove-logo').style.display = 'inline-block';
      }

      triggerPreview();
    } catch (err) {
      API.showToast('Failed to load QR code data', 'error');
    }
  }

  function setupFormSubmit() {
    const form = document.getElementById('qr-form');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const saveBtn = document.getElementById('btn-save-qr');
      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
      }

      const payload = extractFormData();

      try {
        let res;
        if (isEditMode) {
          payload.id = qrId;
          res = await API.post('/qr/update.php', payload);
          API.showToast('QR Code updated successfully!', 'success');
        } else {
          res = await API.post('/qr/create.php', payload);
          API.showToast('QR Code created successfully!', 'success');
        }

        const root = API.getRoot();
        setTimeout(() => {
          window.location.href = `${root}/public/dashboard.html`;
        }, 800);
      } catch (err) {
        if (saveBtn) {
          saveBtn.disabled = false;
          saveBtn.innerHTML = isEditMode ? '<i class="fas fa-save"></i> Save Changes' : '<i class="fas fa-magic"></i> Create QR Code';
        }
        API.showToast(err.message || 'Operation failed', 'error');
      }
    });
  }

  return {
    init,
    triggerPreview,
  };
})();
