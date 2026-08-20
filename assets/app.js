(() => {
  const menuButton = document.querySelector('[data-menu-toggle]');
  const menu = document.querySelector('[data-menu]');
  menuButton?.addEventListener('click', () => menu?.classList.toggle('open'));

  const offers = [...document.querySelectorAll('.offer-item')];
  if (offers.length > 1) {
    let current = 0;
    window.setInterval(() => {
      offers[current].classList.add('is-hidden');
      current = (current + 1) % offers.length;
      offers[current].classList.remove('is-hidden');
    }, 4200);
  }

  document.querySelectorAll('.copy-coupon').forEach((button) => {
    button.addEventListener('click', async () => {
      try { await navigator.clipboard.writeText(button.dataset.code || ''); } catch (_) {}
      const original = button.textContent;
      button.textContent = 'Copied';
      window.setTimeout(() => { button.textContent = original; }, 1500);
    });
  });
})();
