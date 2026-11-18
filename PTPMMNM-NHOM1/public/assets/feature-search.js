'use strict';
/** Tìm kiếm trong dữ liệu PHP đã được lọc theo quyền; không gọi API riêng. */
(function () {
  const normalize = (value) => String(value || '')
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[đĐ]/g, 'd').toLowerCase().trim();

  document.querySelectorAll('[data-feature-catalog]').forEach((catalog) => {
    const input = catalog.querySelector('[data-feature-catalog-input]');
    const items = Array.from(catalog.querySelectorAll('[data-feature-catalog-item]'));
    const count = catalog.querySelector('[data-feature-catalog-count]');
    const empty = catalog.querySelector('[data-feature-catalog-empty]');
    if (!input) return;
    const update = () => {
      const words = normalize(input.value).split(/\s+/).filter(Boolean);
      let shown = 0;
      items.forEach((item) => {
        const haystack = normalize(item.dataset.searchText);
        const visible = words.every((word) => haystack.includes(word));
        item.hidden = !visible;
        if (visible) shown++;
      });
      if (count) count.textContent = String(shown);
      if (empty) empty.hidden = shown !== 0;
    };
    input.addEventListener('input', update);
    update();
  });

  document.querySelectorAll('[data-feature-search]').forEach((widget) => {
    const input = widget.querySelector('[data-feature-search-input]');
    const list = widget.querySelector('[data-feature-search-suggestions]');
    const cards = Array.from(document.querySelectorAll('[data-feature-card]'));
    if (!input || !list) return;
    let activeIndex = -1;
    const close = () => {
      list.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      activeIndex = -1;
    };
    const open = () => {
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    };
    const render = () => {
      const words = normalize(input.value).split(/\s+/).filter(Boolean);
      const matched = cards.filter((card) => {
        const haystack = normalize([card.dataset.featureName, card.dataset.featureKey, card.dataset.featureDescription].join(' '));
        return words.every((word) => haystack.includes(word));
      });
      list.replaceChildren();
      activeIndex = -1;
      if (matched.length === 0) {
        const message = document.createElement('div');
        message.className = 'ff-search-no-match';
        message.textContent = 'Không tìm thấy tính năng phù hợp.';
        list.appendChild(message);
      }
      matched.forEach((card) => {
        const link = document.createElement('a');
        link.className = 'ff-search-suggestion';
        link.href = '#' + card.id;
        link.setAttribute('role', 'option');
        const icon = document.createElement('i');
        icon.className = 'bi bi-lightning-charge';
        const body = document.createElement('span');
        body.className = 'ff-search-suggestion-info';
        const title = document.createElement('strong');
        title.textContent = card.dataset.featureName || 'Tính năng';
        const code = document.createElement('small');
        code.textContent = card.dataset.featureKey || '';
        body.append(title, code);
        const state = document.createElement('span');
        state.className = 'ff-search-suggestion-status';
        const enabled = card.dataset.featureEnabled === '1';
        state.textContent = enabled ? 'Được sử dụng' : 'Chưa được cấp';
        if (!enabled) state.classList.add('is-locked');
        link.append(icon, body, state);
        link.addEventListener('click', () => {
          close();
          input.value = card.dataset.featureName || '';
          cards.forEach((el) => el.removeAttribute('data-feature-highlight'));
          card.setAttribute('data-feature-highlight', '1');
          // Link fragment cùng trang, không tạo quyền sử dụng một flag đang khóa.
          card.scrollIntoView({ behavior: 'smooth', block: 'center' });
          card.focus({ preventScroll: true });
        });
        list.appendChild(link);
      });
      open();
    };
    input.addEventListener('focus', render);
    input.addEventListener('click', render);
    input.addEventListener('input', render);
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {close(); input.blur(); return;}
      if (list.hidden) return;
      const links = Array.from(list.querySelectorAll('.ff-search-suggestion'));
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        if (!links.length) return;
        event.preventDefault();
        activeIndex = event.key === 'ArrowDown'
          ? (activeIndex + 1) % links.length
          : (activeIndex - 1 + links.length) % links.length;
        links.forEach((link, i) => link.classList.toggle('is-key-active', i === activeIndex));
        links[activeIndex].scrollIntoView({ block: 'nearest' });
      } else if (event.key === 'Enter' && links.length) {
        event.preventDefault();
        links[Math.max(activeIndex, 0)].click();
      }
    });
    document.addEventListener('pointerdown', (event) => {
      if (!widget.contains(event.target)) close();
    });
    if (location.hash && /^#feature-\d+$/.test(location.hash)) {
      const highlighted = cards.find((card) => '#' + card.id === location.hash);
      if (highlighted) highlighted.setAttribute('data-feature-highlight', '1');
    }
  });
})();
