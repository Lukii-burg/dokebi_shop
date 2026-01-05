(() => {
  const toggles = document.querySelectorAll('.password-toggle');
  if (!toggles.length) return;

  toggles.forEach((btn) => {
    const targetId = btn.getAttribute('data-target');
    const input = targetId ? document.getElementById(targetId) : btn.previousElementSibling;
    if (!input) return;

    const setVisibility = (visible) => {
      input.type = visible ? 'text' : 'password';
      btn.classList.toggle('is-visible', visible);
      btn.setAttribute('aria-pressed', visible ? 'true' : 'false');
      btn.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
    };

    btn.addEventListener('click', () => {
      const show = input.type === 'password';
      setVisibility(show);
      input.focus();
    });

    // Initialize state: start visible so first tap hides
    setVisibility(true);
  });
})();
