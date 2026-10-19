const themeButtons = document.querySelectorAll('[data-theme-toggle]');

const updateThemeButtons = () => {
    const isDark = document.documentElement.classList.contains('dark');
    const label = isDark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro';
    themeButtons.forEach((button) => {
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
    });
};

themeButtons.forEach((button) => {
    button.addEventListener('click', () => {
        const isDark = document.documentElement.classList.toggle('dark');
        try {
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        } catch {}
        updateThemeButtons();
    });
});

updateThemeButtons();
