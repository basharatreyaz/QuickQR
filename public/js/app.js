/**
 * Common Application Utilities
 */
const App = (() => {
  function copyToClipboard(text, successMessage = 'Copied to clipboard!') {
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(() => {
        API.showToast(successMessage, 'success');
      }).catch(() => {
        fallbackCopy(text, successMessage);
      });
    } else {
      fallbackCopy(text, successMessage);
    }
  }

  function fallbackCopy(text, successMessage) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
      document.execCommand('copy');
      API.showToast(successMessage, 'success');
    } catch (err) {
      API.showToast('Unable to copy text', 'error');
    }
    textArea.remove();
  }

  function formatDate(dateStr) {
    if (!dateStr) return 'Never';
    const date = new Date(dateStr);
    return date.toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  }

  function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.add('active');
    }
  }

  function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.remove('active');
    }
  }

  function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
      sidebar.classList.toggle('open');
    }
  }

  function showToast(message, type = 'info', duration = 3500) {
    if (typeof API !== 'undefined' && typeof API.showToast === 'function') {
      return API.showToast(message, type, duration);
    }
  }

  return {
    copyToClipboard,
    formatDate,
    openModal,
    closeModal,
    toggleSidebar,
    showToast
  };
})();
