/* CommunityPass dedicated Dark Mode page: persist account preference throughout the web UI. */
(() => {
  const workspace = document.querySelector('.ff-feature-shell[data-feature-slug="dark"]');
  if (!workspace) return;
  const mode = workspace.getAttribute('data-dark-active') === '1' ? 'dark' : 'light';
  document.body.setAttribute('data-ff-theme', mode);
  // Keep the existing topbar theme preference in sync across pages in this browser.
  try { localStorage.setItem('flagflow-color-mode',mode); } catch (_) {}
})();
