'use strict';

// Las longitudes HTML se cuentan en UTF-16; validar caracteres Unicode como el servidor.
document.querySelectorAll('[data-max-caracteres]').forEach((input) => {
    const comprobar = () => {
        const value = input.value.trim();
        const limit = Number(input.dataset.maxCaracteres);
        input.setCustomValidity(Array.from(value).length > limit
            ? `Utilice como máximo ${limit} caracteres.`
            : (input.required && value === '' ? 'Este campo es obligatorio.' : ''));
    };
    input.addEventListener('input', comprobar);
    comprobar();
});

document.querySelectorAll('[data-licencia-conductor]').forEach((input) => {
    const convertirAMayusculas = () => {
        const inicio = input.selectionStart;
        const fin = input.selectionEnd;
        input.value = input.value.replace(/[a-z]/g, (letra) => letra.toUpperCase());
        if (inicio !== null && fin !== null) input.setSelectionRange(inicio, fin);
    };
    input.addEventListener('input', convertirAMayusculas);
    convertirAMayusculas();
});

document.querySelectorAll('[data-form-usuario]').forEach((form) => {
    const password = form.querySelector('[name="contrasena"]');
    const confirmation = form.querySelector('[name="confirmacion"]');
    if (!password || !confirmation) return;
    const comprobar = () => {
        const value = password.value;
        const invalid = value !== '' && (Array.from(value).length < 12
            || new TextEncoder().encode(value).length > 72 || value.trim() === '' || value.includes('\0'));
        password.setCustomValidity(invalid ? 'Use al menos 12 caracteres y como máximo 72 bytes.' : '');
        confirmation.setCustomValidity(confirmation.value !== '' && confirmation.value !== value
            ? 'La confirmación debe coincidir con la contraseña.' : '');
    };
    password.addEventListener('input', comprobar);
    confirmation.addEventListener('input', comprobar);
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            password.value = '';
            confirmation.value = '';
        }
        comprobar();
    });
    comprobar();
});
