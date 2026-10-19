const interactiveElements = 'a, button, input, select, textarea, label';

document.addEventListener('click', (event) => {
    const row = event.target.closest('[data-clickable-row]');
    if (!row || event.target.closest(interactiveElements) || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || window.getSelection()?.toString()) {
        return;
    }

    window.location.assign(row.dataset.href);
});

document.addEventListener('keydown', (event) => {
    if (event.target.matches('[data-clickable-row]') && ['Enter', ' '].includes(event.key)) {
        event.preventDefault();
        window.location.assign(event.target.dataset.href);
    }
});
