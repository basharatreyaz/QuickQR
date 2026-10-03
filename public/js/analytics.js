/**
 * QR Analytics & Statistics Controller
 */
const Analytics = (() => {
  let qrId = null;

  async function init() {
    await Auth.requireAuth();

    const urlParams = new URLSearchParams(window.location.search);
    qrId = urlParams.get('id');

    if (!qrId) {
      await loadQrSelector();
      return;
    }

    loadAnalytics(qrId);
  }

  async function loadQrSelector() {
    try {
      const res = await API.get('/qr/list.php', { limit: 50 });
      const items = res.data.items || [];

      if (items.length === 0) {
        document.getElementById('analytics-content').innerHTML = `
          <div class="empty-state">
            <div class="empty-state-icon"><i class="fas fa-chart-line"></i></div>
            <h3>No QR Codes Found</h3>
            <p>Create your first dynamic QR code to start tracking scans and visitor metrics.</p>
            <a href="qr-create.html" class="btn btn-primary"><i class="fas fa-plus"></i> Create QR Code</a>
          </div>
        `;
        return;
      }

      // Default to the first QR code
      qrId = items[0].id;
      populateQrDropdown(items, qrId);
      loadAnalytics(qrId);
    } catch (err) {
      API.showToast('Failed to load QR list.', 'error');
    }
  }

  function populateQrDropdown(items, selectedId) {
    const selectorContainer = document.getElementById('qr-selector-container');
    if (!selectorContainer) return;

    selectorContainer.innerHTML = `
      <label class="form-label" style="margin-bottom:0;margin-right:0.75rem;font-size:0.875rem;">Switch QR:</label>
      <select id="qr-select-dropdown" class="form-control" style="width:auto;display:inline-block;">
        ${items.map(i => `<option value="${i.id}" ${i.id == selectedId ? 'selected' : ''}>${escapeHtml(i.name)} (${i.scan_count} scans)</option>`).join('')}
      </select>
    `;

    document.getElementById('qr-select-dropdown').addEventListener('change', (e) => {
      qrId = e.target.value;
      const root = API.getRoot();
      window.history.pushState({}, '', `${root}/public/analytics.html?id=${qrId}`);
      loadAnalytics(qrId);
    });
  }

  async function loadAnalytics(id) {
    try {
      const res = await API.get('/analytics/stats.php', { qr_id: id });
      const { qr, stats } = res.data;

      // Update Header
      document.getElementById('qr-title-heading').textContent = qr.name;
      document.getElementById('qr-short-link').textContent = `/r/${qr.short_code}`;
      document.getElementById('stat-total-scans').textContent = stats.total_scans.toLocaleString();
      const todayEl = document.getElementById('stat-today-scans');
      if (todayEl) {
        todayEl.textContent = (stats.today_scans || 0).toLocaleString();
      }
      document.getElementById('stat-unique-scans').textContent = stats.unique_scans.toLocaleString();

      const uniquePct = stats.total_scans > 0 ? Math.round((stats.unique_scans / stats.total_scans) * 100) : 0;
      document.getElementById('stat-unique-ratio').textContent = `${uniquePct}%`;

      // Render Visualizations
      renderTimeline(stats.timeline);
      renderBarChart('device-breakdown', stats.devices, 'device_type', stats.total_scans);
      renderBarChart('os-breakdown', stats.operating_systems, 'os', stats.total_scans);
      renderBarChart('browser-breakdown', stats.browsers, 'browser', stats.total_scans);
      renderBarChart('country-breakdown', stats.countries, 'country', stats.total_scans);
      renderBarChart('referrer-breakdown', stats.referrers, 'referrer', stats.total_scans);

      // Render Recent Scans Table
      renderRecentScans(stats.recent);
    } catch (err) {
      API.showToast('Failed to load analytics data.', 'error');
    }
  }

  function renderTimeline(timeline) {
    const container = document.getElementById('timeline-chart');
    if (!container) return;

    if (!timeline || timeline.length === 0) {
      container.innerHTML = '<div style="text-align:center;padding:3rem 1rem;color:var(--slate-400);">No scan activity recorded in this period yet.</div>';
      return;
    }

    const maxCount = Math.max(...timeline.map(t => parseInt(t.scan_count, 10)), 1);

    let barsHtml = `
      <div style="display:flex;align-items:flex-end;gap:0.75rem;height:200px;padding-top:1.5rem;border-bottom:1px solid var(--slate-200);overflow-x:auto;">
    `;

    timeline.forEach(t => {
      const count = parseInt(t.scan_count, 10);
      const heightPct = Math.max(8, Math.round((count / maxCount) * 100));
      const dateParts = t.scan_date.split('-');
      const label = `${dateParts[1]}/${dateParts[2]}`;

      barsHtml += `
        <div style="flex:1;min-width:32px;display:flex;flex-direction:column;align-items:center;height:100%;justify-content:flex-end;" title="${t.scan_date}: ${count} scans (${t.unique_count} unique)">
          <span style="font-size:0.7rem;font-weight:700;color:var(--primary);margin-bottom:0.25rem;">${count}</span>
          <div style="width:100%;max-width:24px;background:var(--primary);border-radius:4px 4px 0 0;height:${heightPct}%;transition:height 0.4s ease;"></div>
          <span style="font-size:0.7rem;color:var(--slate-500);margin-top:0.5rem;white-space:nowrap;">${label}</span>
        </div>
      `;
    });

    barsHtml += `</div>`;
    container.innerHTML = barsHtml;
  }

  function renderBarChart(containerId, items, labelKey, total) {
    const container = document.getElementById(containerId);
    if (!container) return;

    if (!items || items.length === 0) {
      container.innerHTML = '<p style="color:var(--slate-400);font-size:0.85rem;padding:1rem 0;">No data recorded yet.</p>';
      return;
    }

    let html = '';
    items.forEach(item => {
      const count = parseInt(item.count, 10);
      const pct = total > 0 ? Math.round((count / total) * 100) : 0;
      const label = item[labelKey] || 'Unknown';

      html += `
        <div class="chart-bar-container">
          <div class="chart-bar-label" title="${escapeHtml(label)}">${escapeHtml(label)}</div>
          <div class="chart-bar-track">
            <div class="chart-bar-fill" style="width: ${pct}%"></div>
          </div>
          <div class="chart-bar-value">${count} <span style="font-size:0.7rem;color:var(--slate-400);">(${pct}%)</span></div>
        </div>
      `;
    });

    container.innerHTML = html;
  }

  function renderRecentScans(scans) {
    const tbody = document.getElementById('recent-scans-tbody');
    if (!tbody) return;

    if (!scans || scans.length === 0) {
      tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--slate-400);padding:2rem;">No recent scans yet.</td></tr>';
      return;
    }

    tbody.innerHTML = scans.map(s => `
      <tr>
        <td><strong>${formatDateTime(s.scanned_at)}</strong></td>
        <td><i class="fas fa-${getDeviceIcon(s.device_type)}" style="color:var(--slate-500);margin-right:0.35rem;"></i> ${escapeHtml(s.device_type)}</td>
        <td>${escapeHtml(s.os)} &bull; ${escapeHtml(s.browser)}</td>
        <td><i class="fas fa-globe-americas" style="color:var(--secondary);margin-right:0.35rem;"></i> ${escapeHtml(s.country)}</td>
        <td style="color:var(--slate-600);">${escapeHtml(s.referrer)}</td>
      </tr>
    `).join('');
  }

  function getDeviceIcon(device) {
    if (device === 'Mobile') return 'mobile-alt';
    if (device === 'Tablet') return 'tablet-alt';
    return 'desktop';
  }

  function formatDateTime(str) {
    if (!str) return 'Just now';
    const d = new Date(str);
    return d.toLocaleString(undefined, {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
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
  };
})();
