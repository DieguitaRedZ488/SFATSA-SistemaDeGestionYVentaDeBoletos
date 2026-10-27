'use strict';

document.querySelectorAll('[data-placa-captura]').forEach((captura) => {
    const completa = captura.querySelector('[name="placa"]');
    const grupos = [...captura.querySelectorAll('[data-placa-grupo]')];
    const contador = captura.querySelector('[data-placa-conteo]');
    const mensaje = captura.querySelector('[data-placa-mensaje]');
    const formulario = completa?.form;
    if (!completa || grupos.length !== 3 || !formulario) return;

    const mayusculasAscii = (texto) => texto.replace(/[a-z]/g, (letra) => letra.toUpperCase());
    const alfanumericos = (texto) => mayusculasAscii(texto).replace(/[^A-Z0-9]/g, '');
    let interactuo = false;
    const teniaValor = completa.value !== '';

    const actualizar = () => {
        const valores = grupos.map((grupo) => grupo.value);
        const total = valores.reduce((n, valor) => n + alfanumericos(valor).length, 0);
        const vacio = valores.some((valor) => valor === '');
        const caracteresInvalidos = valores.some((valor) => !/^[A-Z0-9]*$/.test(valor));
        const aviso = caracteresInvalidos ? 'Use únicamente letras A-Z y números 0-9 en cada grupo.'
            : vacio ? 'Complete los tres grupos; ninguno puede quedar vacío. El total debe ser 7 caracteres.'
                : total !== 7 ? `Capture exactamente 7 caracteres alfanuméricos; actualmente hay ${total}.` : '';
        completa.value = valores.join('-');
        contador.textContent = `${total}/7 caracteres alfanuméricos`;
        mensaje.textContent = aviso;
        mensaje.hidden = aviso === '' || (!interactuo && !teniaValor);
        grupos.forEach((grupo, i) => {
            const usadosEnOtros = valores.reduce((n, valor, j) => n + (i === j ? 0 : alfanumericos(valor).length), 0);
            grupo.maxLength = Math.max(0, 7 - usadosEnOtros);
            grupo.setCustomValidity(aviso);
            if (aviso !== '' && (interactuo || teniaValor)) grupo.setAttribute('aria-invalid', 'true');
            else grupo.removeAttribute('aria-invalid');
        });
    };

    completa.type = 'hidden';
    completa.required = false;
    captura.querySelector('[data-placa-fallback]').hidden = true;
    captura.querySelector('[data-placa-grupos]').hidden = false;
    grupos.forEach((grupo) => {
        grupo.disabled = false;
        grupo.required = true;
        // No reparar ni truncar silenciosamente una placa heredada al abrir la edición.
        grupo.value = mayusculasAscii(grupo.value);
        const capturar = () => {
            interactuo = true;
            const usados = grupos.reduce((n, otro) => n + (otro === grupo ? 0 : alfanumericos(otro.value).length), 0);
            grupo.value = alfanumericos(grupo.value).slice(0, Math.max(0, 7 - usados));
            actualizar();
        };
        grupo.addEventListener('input', capturar);
        grupo.addEventListener('change', capturar);
    });
    formulario.addEventListener('submit', (evento) => {
        interactuo = true;
        actualizar();
        if (grupos.some((grupo) => !grupo.checkValidity())) {
            evento.preventDefault();
            grupos.find((grupo) => !grupo.checkValidity())?.focus();
        }
    });
    formulario.addEventListener('reset', () => { setTimeout(actualizar, 0); });
    actualizar();
});
