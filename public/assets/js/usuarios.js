'use strict';

document.querySelectorAll('[data-capacidad-vehiculo]').forEach((input) => {
    const comprobar = () => {
        const valor = input.value;
        input.setCustomValidity(/^[0-9]+$/.test(valor) && Number(valor) >= 1 && Number(valor) <= 30
            ? '' : 'La capacidad debe estar entre 1 y 30 pasajeros');
    };
    input.addEventListener('input', comprobar);
    input.addEventListener('change', comprobar);
    comprobar();
});

document.querySelectorAll('[data-telefono]').forEach((input) => {
    const comprobar = () => input.setCustomValidity((input.value === '' && !input.required) || /^[0-9]{10}$/.test(input.value)
        ? '' : 'El teléfono debe contener exactamente 10 dígitos');
    input.addEventListener('beforeinput', (evento) => {
        // Rechazar prefijos/formato pegado en lugar de transformarlos en otro teléfono.
        const texto = evento.dataTransfer?.getData('text/plain') ?? evento.data;
        if (typeof texto === 'string' && /[^0-9]/.test(texto)) evento.preventDefault();
    });
    input.addEventListener('input', () => {
        input.value = input.value.replace(/[^0-9]/g, '').slice(0, 10);
        comprobar();
    });
    input.addEventListener('change', comprobar);
    // No normalizar ni recortar datos antiguos al abrir el formulario.
    comprobar();
});

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
