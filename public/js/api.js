/**
 * QuickQR SaaS - API Client & Notifications
 */
const API = (() => {
  // Determine root path automatically
  const path = window.location.pathname;
  let root = '';
  if (path === '/sass' || path.startsWith('/sass/')) {
    root = '/sass';
  }

  const BASE_URL = root + '/api';

  function showToast(message, type = 'info', duration = 3500) {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    let icon = 'info-circle';
    if (type === 'success') icon = 'check-circle';
    if (type === 'error') icon = 'exclamation-circle';

    toast.innerHTML = `
      <i class="fas fa-${icon}" style="font-size: 1.1rem; color: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#0ea5e9'}"></i>
      <span style="flex:1;">${message}</span>
      <button onclick="this.parentElement.remove()" style="background:none;border:none;color:#94a3b8;cursor:pointer;font-size:0.9rem;">&times;</button>
    `;

    container.appendChild(toast);

    setTimeout(() => {
      if (toast.parentElement) {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(50px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
      }
    }, duration);
  }

  async function request(endpoint, options = {}) {
    const url = endpoint.startsWith('http') ? endpoint : `${BASE_URL}${endpoint}`;

    const headers = {
      'Accept': 'application/json',
      ...(options.headers || {})
    };

    if (!(options.body instanceof FormData) && options.body && typeof options.body === 'object') {
      headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(options.body);
    }

    try {
      const response = await fetch(url, {
        ...options,
        headers,
        credentials: 'same-origin'
      });

      // Handle non-JSON or download responses directly
      const contentType = response.headers.get('content-type') || '';
      if (!contentType.includes('application/json')) {
        if (!response.ok) {
          throw new Error(`HTTP Error: ${response.status}`);
        }
        return response;
      }

      const data = await response.json();

      if (!response.ok || data.success === false) {
        const errorMsg = data?.error?.message || `Request failed with status ${response.status}`;

        const currentPath = window.location.pathname.toLowerCase();
        const isPublicPage = currentPath.endsWith('/index.html') || 
                             currentPath.endsWith('/index') || 
                             currentPath.endsWith('/') || 
                             currentPath.endsWith('/sass') ||
                             currentPath.includes('/login') || 
                             currentPath.includes('/register') || 
                             currentPath.includes('/pricing') ||
                             currentPath.includes('/forgot-password') ||
                             currentPath.includes('/reset-password') ||
                             currentPath.includes('/privacy') ||
                             currentPath.includes('/terms') ||
                             currentPath.includes('/faq') ||
                             currentPath.includes('/contact') ||
                             currentPath.includes('/docs');

        if (response.status === 401 && !options.skipAuthRedirect && !isPublicPage) {
          showToast('Session expired. Redirecting to login...', 'error');
          setTimeout(() => {
            window.location.href = `${root}/public/login.html`;
          }, 1000);
        }

        const err = new Error(errorMsg);
        err.code = data?.error?.code || 'ERROR';
        err.details = data?.error?.details;
        throw err;
      }

      return data;
    } catch (err) {
      console.error('API Error:', err);
      throw err;
    }
  }

  return {
    get: (endpoint, params = {}, options = {}) => {
      const query = new URLSearchParams(params).toString();
      const url = query ? `${endpoint}?${query}` : endpoint;
      return request(url, { ...options, method: 'GET' });
    },
    post: (endpoint, body, options = {}) => request(endpoint, { ...options, method: 'POST', body }),
    delete: (endpoint, body, options = {}) => request(endpoint, { ...options, method: 'POST', body }),
    showToast,
    getRoot: () => root,
    getBaseUrl: () => BASE_URL,
  };
})();
