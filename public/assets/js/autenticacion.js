'use strict';

document.querySelector('[data-error-focus]')?.focus();

document.querySelectorAll('[data-form-acceso]').forEach((form) => {
    const button = form.querySelector('[data-submit]');
    const label = button?.querySelector('[data-button-label]');
    const spinner = button?.querySelector('.loading-indicator');
    const status = form.querySelector('[data-submit-status]');
    if (!button || !label || !spinner || !status) return;
    const original = label.textContent;
    form.addEventListener('submit', (event) => {
        if (!form.checkValidity() || button.disabled) {
            event.preventDefault();
            return;
        }
        button.disabled = true;
        form.setAttribute('aria-busy', 'true');
        spinner.hidden = false;
        label.textContent = 'Procesando…';
        status.textContent = 'Procesando la solicitud.';
    });
    window.addEventListener('pageshow', (event) => {
        button.disabled = false;
        form.removeAttribute('aria-busy');
        spinner.hidden = true;
        label.textContent = original;
        status.textContent = '';
        if (event.persisted) {
            const password = form.querySelector('input[type="password"]');
            if (password) password.value = '';
        }
    });
});
