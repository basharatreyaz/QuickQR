/**
 * Dashboard Controller
 */
const Dashboard = (() => {
  let currentQrList = [];
  let currentFilters = {
    search: '',
    status: '',
    type: '',
    page: 1,
    limit: 12
  };
  let pendingDeleteId = null;

  async function init() {
    await Auth.requireAuth();
    loadOverview();
    loadQrCodes();
    setupEventListeners();
  }

  async function loadOverview() {
    try {
      const res = await API.get('/analytics/stats.php');
      const data = res.data;

      document.getElementById('stat-total-qrs').textContent = data.total_qrs || 0;
      document.getElementById('stat-active-qrs').textContent = data.active_qrs || 0;
      document.getElementById('stat-total-scans').textContent = Number(data.total_scans || 0).toLocaleString();
    } catch (err) {
      console.error('Error loading overview:', err);
    }
  }

  async function loadQrCodes() {
    const grid = document.getElementById('qr-grid');
    const loading = document.getElementById('qr-loading');
    const emptyState = document.getElementById('qr-empty');

    if (loading) loading.style.display = 'block';
    if (grid) grid.style.display = 'none';
    if (emptyState) emptyState.style.display = 'none';

    try {
      const res = await API.get('/qr/list.php', currentFilters);
      currentQrList = res.data.items || [];
      const pagination = res.data.pagination;

      if (loading) loading.style.display = 'none';

      if (currentQrList.length === 0) {
        if (emptyState) emptyState.style.display = 'block';
        return;
      }

      if (grid) {
        grid.style.display = 'grid';
        renderQrGrid(currentQrList);
      }

      renderPagination(pagination);
    } catch (err) {
      if (loading) loading.style.display = 'none';
      API.showToast('Failed to load QR codes.', 'error');
    }
  }

  function renderQrGrid(items) {
    const grid = document.getElementById('qr-grid');
    const root = API.getRoot();

    grid.innerHTML = items.map(qr => {
      const isPaused = qr.status === 'paused';
      const statusBadge = isPaused
        ? '<span class="badge badge-warning"><i class="fas fa-pause-circle"></i> Paused</span>'
        : '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Active</span>';

      return `
        <div class="qr-card" data-id="${qr.id}">
          <div class="qr-card-header">
            <div>
              <div class="qr-card-title">${escapeHtml(qr.name)}</div>
              <div class="qr-card-meta"><i class="fas fa-calendar-alt"></i> ${App.formatDate(qr.created_at)} &bull; ${qr.type.toUpperCase()}</div>
            </div>
            ${statusBadge}
          </div>

          <div class="qr-preview-box" id="preview-${qr.id}">
            <div style="color:var(--slate-400);font-size:0.85rem;"><i class="fas fa-spinner fa-spin"></i> Rendering...</div>
          </div>

          <div class="qr-card-dest" title="${escapeHtml(qr.destination)}">
            <i class="fas fa-link" style="color:var(--slate-400);margin-right:0.35rem;"></i>
            <strong>Target:</strong> ${escapeHtml(qr.destination)}
          </div>

          <div style="font-size:0.8rem;color:var(--slate-500);margin-bottom:0.75rem;display:flex;align-items:center;justify-content:space-between;">
            <span><i class="fas fa-chart-bar" style="color:var(--primary);"></i> <strong>${qr.scan_count}</strong> scans</span>
            <button onclick="App.copyToClipboard('${qr.short_url}', 'QR short link copied!')" class="btn btn-outline btn-sm" style="padding:0.2rem 0.5rem;font-size:0.75rem;">
              <i class="fas fa-copy"></i> /r/${qr.short_code}
            </button>
          </div>

          <div class="qr-card-footer">
            <div class="qr-actions">
              <a href="${root}/public/analytics.html?id=${qr.id}" class="btn btn-outline btn-sm" title="View Analytics">
                <i class="fas fa-chart-line"></i>
              </a>
              <a href="${root}/public/qr-edit.html?id=${qr.id}" class="btn btn-outline btn-sm" title="Edit Design & Target">
                <i class="fas fa-edit"></i>
              </a>
              <button onclick="Dashboard.toggleStatus(${qr.id})" class="btn btn-outline btn-sm" title="${isPaused ? 'Activate' : 'Pause'}">
                <i class="fas fa-${isPaused ? 'play' : 'pause'}"></i>
              </button>
              <button onclick="Dashboard.duplicateQr(${qr.id})" class="btn btn-outline btn-sm" title="Duplicate">
                <i class="fas fa-clone"></i>
              </button>
              <button onclick="Dashboard.openDeleteModal(${qr.id})" class="btn btn-outline btn-sm" style="color:var(--danger);" title="Delete">
                <i class="fas fa-trash-alt"></i>
              </button>
            </div>

            <div class="dropdown">
              <button onclick="Dashboard.downloadQr(${qr.id}, 'png')" class="btn btn-primary btn-sm">
                <i class="fas fa-download"></i> PNG
              </button>
            </div>
          </div>
        </div>
      `;
    }).join('');

    // Load previews in background
    items.forEach(qr => {
      fetchPreviewForCard(qr);
    });
  }

  async function fetchPreviewForCard(qr) {
    const box = document.getElementById(`preview-${qr.id}`);
    if (!box) return;

    try {
      const res = await API.get('/qr/preview.php', {
        type: 'url',
        destination: qr.short_url,
        config: JSON.stringify(qr.config || {}),
        format: 'svg'
      });
      if (res.data?.svg) {
        box.innerHTML = res.data.svg;
      }
    } catch (e) {
      box.innerHTML = '<span style="color:var(--slate-400);font-size:0.8rem;">Preview unavailable</span>';
    }
  }

  function renderPagination(pagination) {
    const container = document.getElementById('pagination-container');
    if (!container || !pagination || pagination.total_pages <= 1) {
      if (container) container.innerHTML = '';
      return;
    }

    let html = `<div style="display:flex;justify-content:center;gap:0.5rem;margin-top:2rem;">`;
    for (let p = 1; p <= pagination.total_pages; p++) {
      const active = p === pagination.page ? 'btn-primary' : 'btn-outline';
      html += `<button onclick="Dashboard.goToPage(${p})" class="btn ${active} btn-sm">${p}</button>`;
    }
    html += `</div>`;
    container.innerHTML = html;
  }

  function goToPage(page) {
    currentFilters.page = page;
    loadQrCodes();
  }

  async function toggleStatus(id) {
    try {
      const res = await API.post('/qr/toggle-status.php', { id });
      API.showToast(res.message, 'success');
      loadOverview();
      loadQrCodes();
    } catch (err) {
      API.showToast(err.message || 'Failed to toggle status.', 'error');
    }
  }

  async function duplicateQr(id) {
    try {
      const res = await API.post('/qr/duplicate.php', { id });
      API.showToast('QR Code duplicated successfully.', 'success');
      loadOverview();
      loadQrCodes();
    } catch (err) {
      API.showToast(err.message || 'Failed to duplicate QR code.', 'error');
    }
  }

  function openDeleteModal(id) {
    pendingDeleteId = id;
    const qr = currentQrList.find(x => x.id === id);
    const nameEl = document.getElementById('delete-qr-name');
    if (nameEl) nameEl.textContent = qr ? qr.name : 'this QR code';
    App.openModal('delete-modal');
  }

  async function confirmDelete() {
    if (!pendingDeleteId) return;
    try {
      await API.post('/qr/delete.php', { id: pendingDeleteId });
      API.showToast('QR Code deleted.', 'info');
      App.closeModal('delete-modal');
      pendingDeleteId = null;
      loadOverview();
      loadQrCodes();
    } catch (err) {
      API.showToast(err.message || 'Delete failed.', 'error');
    }
  }

  function downloadQr(id, format) {
    const root = API.getRoot();
    window.location.href = `${root}/api/qr/download.php?id=${id}&format=${format}`;
  }

  function setupEventListeners() {
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
      let debounce;
      searchInput.addEventListener('input', (e) => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
          currentFilters.search = e.target.value.trim();
          currentFilters.page = 1;
          loadQrCodes();
        }, 300);
      });
    }

    const statusFilter = document.getElementById('status-filter');
    if (statusFilter) {
      statusFilter.addEventListener('change', (e) => {
        currentFilters.status = e.target.value;
        currentFilters.page = 1;
        loadQrCodes();
      });
    }
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  return {
    init,
    toggleStatus,
    duplicateQr,
    openDeleteModal,
    confirmDelete,
    downloadQr,
    goToPage,
  };
})();
