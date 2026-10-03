/**
 * Authentication management
 */
const Auth = (() => {
  let currentUser = null;

  async function checkUser() {
    try {
      const res = await API.get('/auth/me.php', {}, { skipAuthRedirect: true });
      currentUser = res.data;
      updateUserUI(currentUser);
      return currentUser;
    } catch (e) {
      currentUser = null;
      return null;
    }
  }

  async function requireAuth() {
    const user = await checkUser();
    if (!user) {
      const root = API.getRoot();
      window.location.href = `${root}/public/login.html?redirect=${encodeURIComponent(window.location.pathname)}`;
    }
    return user;
  }

  async function requireAdmin() {
    const user = await requireAuth();
    if (user.role !== 'admin') {
      API.showToast('Administrator privileges required.', 'error');
      const root = API.getRoot();
      setTimeout(() => {
        window.location.href = `${root}/public/dashboard.html`;
      }, 1200);
    }
    return user;
  }

  function updateUserUI(user) {
    if (!user) return;

    const nameElements = document.querySelectorAll('.auth-user-name');
    nameElements.forEach(el => el.textContent = user.name);

    const emailElements = document.querySelectorAll('.auth-user-email');
    emailElements.forEach(el => el.textContent = user.email);

    const planElements = document.querySelectorAll('.auth-user-plan');
    planElements.forEach(el => el.textContent = user.plan?.name || 'Free');

    const avatarElements = document.querySelectorAll('.auth-user-avatar');
    avatarElements.forEach(el => {
      el.textContent = user.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
    });

    if (user.role === 'admin') {
      const adminNavs = document.querySelectorAll('.admin-only');
      adminNavs.forEach(el => el.style.display = 'flex');
    }

    // Toggle billing visibility
    const isBillingEnabled = Boolean(user.billing_enabled);
    const billingElements = document.querySelectorAll('.billing-only, .billing-nav-item, a[href*="pricing.html"], a[href*="/pricing"]');
    billingElements.forEach(el => {
      // In sidebar, hide the parent <li> if the link is inside a li.sidebar-item
      const target = el.closest('.sidebar-item') || el;
      if (isBillingEnabled || user.role === 'admin') {
        target.style.display = '';
      } else {
        target.style.display = 'none';
      }
    });

    const planTags = document.querySelectorAll('.user-plan-tag');
    planTags.forEach(el => {
      if (!isBillingEnabled && user.role !== 'admin') {
        el.style.display = 'none';
      } else {
        el.style.display = '';
      }
    });
  }

  async function logout() {
    try {
      await API.post('/auth/logout.php');
      API.showToast('Logged out successfully.', 'info');
      const root = API.getRoot();
      setTimeout(() => {
        window.location.href = `${root}/public/login.html`;
      }, 500);
    } catch (e) {
      API.showToast('Logout failed', 'error');
    }
  }

  return {
    checkUser,
    requireAuth,
    requireAdmin,
    getUser: () => currentUser,
    logout,
  };
})();
