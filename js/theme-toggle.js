(() => {
  const root = document.documentElement;
  const checkbox = document.getElementById('themeToggle');
  const STORAGE_KEY = 'dokebi_theme';

  const prefersDark = () =>
    window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;

  const resolveTheme = () => {
    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      if (saved === 'dark' || saved === 'light') return saved;
    } catch (err) {
      /* ignore storage errors */
    }
    const preset = root.getAttribute('data-theme');
    if (preset === 'dark' || preset === 'light') return preset;
    return prefersDark() ? 'dark' : 'light';
  };

  const applyTheme = (mode) => {
    root.setAttribute('data-theme', mode);
    if (checkbox) {
      checkbox.checked = mode === 'dark';
    }
  };

  const initialTheme = resolveTheme();
  applyTheme(initialTheme);

  if (checkbox) {
    checkbox.addEventListener('change', () => {
      const next = checkbox.checked ? 'dark' : 'light';
      applyTheme(next);
      try {
        localStorage.setItem(STORAGE_KEY, next);
      } catch (err) {
        /* ignore storage errors */
      }
    });
  }
})();
