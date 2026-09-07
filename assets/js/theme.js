(function () {
  var STORAGE_KEY = 'scerrs-theme';

  function apply(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem(STORAGE_KEY, theme);
    updateIcon(theme);
  }

  function current() {
    return document.documentElement.getAttribute('data-theme') || 'light';
  }

  function updateIcon(theme) {
    var btn = document.getElementById('themeToggle');
    if (!btn) return;
    var icon = btn.querySelector('i');
    if (!icon) return;
    // Show the icon for the theme you'd switch TO
    icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
  }

  document.addEventListener('DOMContentLoaded', function () {
    updateIcon(current());
    var btn = document.getElementById('themeToggle');
    if (btn) {
      btn.addEventListener('click', function () {
        apply(current() === 'dark' ? 'light' : 'dark');
      });
    }
  });
})();
