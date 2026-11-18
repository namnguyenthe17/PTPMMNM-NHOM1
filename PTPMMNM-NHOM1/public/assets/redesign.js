/** UX only: keyboard search, mobile sidebar, optional color mode. No DB writes. */
(() => {
  'use strict';
  const body = document.body;
  const toggle = document.querySelector('[data-sidebar-toggle]');
  const closeSide = () => {
    body.classList.remove('ff-sidebar-open');
    if (toggle) toggle.setAttribute('aria-expanded','false');
  };
  toggle?.addEventListener('click', () => {
    const willOpen = !body.classList.contains('ff-sidebar-open');
    body.classList.toggle('ff-sidebar-open', willOpen);
    toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
  });
  document.querySelector('[data-close-sidebar]')?.addEventListener('click',closeSide);
  document.querySelectorAll('.ff-navigation a').forEach(el=>el.addEventListener('click',closeSide));
  const input = document.querySelector('.ff-global-search input[type="search"]');
  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
      if(!input) return;
      e.preventDefault();
      input.focus();
      input.select();
    }
    if(e.key === 'Escape') closeSide();
  });
  const themeKey = 'flagflow-color-mode';
  let saved = 'light';
  try { saved = localStorage.getItem(themeKey) || 'light'; } catch {}
  const applyTheme = mode => {
    body.setAttribute('data-ff-theme', mode === 'dark' ? 'dark' : 'light');
    const ico = document.querySelector('[data-theme-toggle] i');
    if(ico) ico.className = mode==='dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
  };
  applyTheme(saved);
  document.querySelector('[data-theme-toggle]')?.addEventListener('click', () => {
    const mode=body.getAttribute('data-ff-theme')==='dark'?'light':'dark';
    applyTheme(mode);
    try { localStorage.setItem(themeKey,mode); } catch {}
  });
})();
