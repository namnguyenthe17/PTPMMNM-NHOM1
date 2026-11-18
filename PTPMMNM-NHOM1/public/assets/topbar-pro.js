/** FlagFlow topbar controls. No external network calls or sample notifications. */
(() => {
  'use strict';
  const body = document.body;
  const button = document.querySelector('[data-theme-toggle]');
  const setMetadata = () => {
    if (!button) return;
    const isDark = body.getAttribute('data-ff-theme') === 'dark';
    button.setAttribute('aria-pressed', String(isDark));
    button.setAttribute('aria-label', isDark ? 'Chuyển chế độ sáng' : 'Chuyển chế độ tối');
    button.setAttribute('title', isDark ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối');
  };
  setMetadata();
  button?.addEventListener('click', () => requestAnimationFrame(setMetadata));
  // Gợi ý ngay khi focus / gõ tìm kiếm; dữ liệu được PHP lọc quyền từ trước.
  const searchForm = document.querySelector('[data-global-search]');
  const searchInput = searchForm?.querySelector('[data-global-search-input]');
  const suggestionMenu = searchForm?.querySelector('[data-global-search-menu]');
  const allSuggestions = Array.from(searchForm?.querySelectorAll('[data-global-search-item]') || []);
  const noResults = searchForm?.querySelector('[data-global-search-empty]');
  const normalize = (value) => String(value || '').normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '').replace(/[đĐ]/g, 'd').toLowerCase().trim();
  let displayed = [];
  let selected = -1;
  const closeSuggestions = () => {
    if (!suggestionMenu || !searchInput) return;
    suggestionMenu.hidden = true;
    searchInput.setAttribute('aria-expanded','false');
    searchInput.removeAttribute('aria-activedescendant');
    selected = -1;
  };
  const selectSuggestion = (index) => {
    selected = index;
    displayed.forEach((item, i) => {
      const active = i === index;
      item.classList.toggle('is-selected', active);
      item.setAttribute('aria-selected', String(active));
      if (active) {
        searchInput?.setAttribute('aria-activedescendant', item.id);
        item.scrollIntoView({block:'nearest'});
      }
    });
    if (index < 0) searchInput?.removeAttribute('aria-activedescendant');
  };
  const updateSuggestions = () => {
    if (!searchInput || !suggestionMenu) return;
    const terms = normalize(searchInput.value).split(/\s+/).filter(Boolean);
    const candidates = allSuggestions.filter(item => {
      const hay = normalize(item.dataset.searchHaystack);
      return terms.every(term => hay.includes(term));
    });
    // Ưu tiên trang chức năng, sau đó là tên dự án và cờ tính năng.
    displayed = candidates.slice(0, 9);
    allSuggestions.forEach((item, i) => {
      item.hidden = !displayed.includes(item);
      item.id = `ff-suggestion-${i}`;
      item.classList.remove('is-selected');
      item.setAttribute('aria-selected','false');
    });
    if (noResults) noResults.hidden = displayed.length > 0;
    selected = -1;
    suggestionMenu.hidden = false;
    searchInput.setAttribute('aria-expanded','true');
    searchInput.removeAttribute('aria-activedescendant');
  };
  searchInput?.addEventListener('focus', updateSuggestions);
  searchInput?.addEventListener('click', updateSuggestions);
  searchInput?.addEventListener('input', updateSuggestions);
  searchInput?.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (!suggestionMenu?.hidden) { e.preventDefault(); e.stopPropagation(); closeSuggestions(); }
      return;
    }
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      if (suggestionMenu?.hidden) updateSuggestions();
      if (!displayed.length) return;
      e.preventDefault();
      const delta = e.key === 'ArrowDown' ? 1 : -1;
      const next = (selected + delta + displayed.length) % displayed.length;
      selectSuggestion(next);
      return;
    }
    if (e.key === 'Enter' && selected >= 0 && displayed[selected]) {
      e.preventDefault();
      window.location.assign(displayed[selected].href);
    }
  });
  document.addEventListener('pointerdown', (e) => {
    if (searchForm && !searchForm.contains(e.target)) closeSuggestions();
  });
  document.addEventListener('focusin', (e) => {
    if (searchForm && !searchForm.contains(e.target)) closeSuggestions();
  });
  // Nút kết quả có thể nhận focus/click tự nhiên; không chặn điều hướng.

  // Escape closes Bootstrap dropdowns in addition to the mobile sidebar.
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape' || e.defaultPrevented || !window.bootstrap?.Dropdown) return;
    document.querySelectorAll('.ff-pro-drop [data-bs-toggle="dropdown"][aria-expanded="true"], .ff-pro-account [data-bs-toggle="dropdown"][aria-expanded="true"]').forEach(el => {
      window.bootstrap.Dropdown.getInstance(el)?.hide();
    });
  });
})();
