document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('menu-toggle');
  const menu = document.getElementById('main-menu');
  const overlay = document.getElementById('menu-overlay');

  const toggleMenu = () => {
    const isOpen = menu.classList.toggle('menu-open');
    toggle.setAttribute('aria-expanded', isOpen);
    toggle.classList.toggle('open');
    overlay.classList.toggle('active', isOpen);

    if (isOpen) {
      menu.querySelectorAll('li').forEach((li, i) => {
        li.style.animation = 'none';
        void li.offsetWidth;
        li.style.animation = `slideInLeft 0.3s forwards`;
        li.style.animationDelay = `${0.1 * i}s`;
      });
    }
  };

  toggle.addEventListener('click', toggleMenu);
  overlay.addEventListener('click', toggleMenu);
});
