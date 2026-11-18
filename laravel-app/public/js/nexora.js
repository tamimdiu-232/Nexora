/**
 * Nexora UI Interactions
 * Minimal plain JavaScript without complex build tools
 */
document.addEventListener('DOMContentLoaded', function () {
  // 1. Mobile Sidebar Toggle
  const sidebarToggle = document.getElementById('nxSidebarToggle');
  const sidebar = document.getElementById('nxSidebar');
  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', function () {
      sidebar.classList.toggle('show');
    });
  }

  // 2. Form Loading Spinners (data-loading)
  document.querySelectorAll('form[data-loading]').forEach(function (form) {
    form.addEventListener('submit', function () {
      const submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn && !submitBtn.disabled) {
        submitBtn.disabled = true;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing...';
      }
    });
  });

  // 3. Confirm Dialogs (data-confirm)
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      const msg = el.getAttribute('data-confirm') || 'Are you sure you want to perform this action?';
      if (!confirm(msg)) {
        e.preventDefault();
      }
    });
  });
});
