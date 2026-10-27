'use strict';

document.querySelectorAll('[data-intermedias]').forEach((grupo) => {
    const formulario = grupo.closest('form');
    const lista = grupo.querySelector('[data-lista-intermedias]');
    const plantilla = grupo.querySelector('[data-plantilla-intermedia]');
    const agregar = grupo.querySelector('[data-agregar-intermedia]');
    const conteo = grupo.querySelector('[data-conteo-intermedias]');
    const error = grupo.querySelector('[data-error-intermedias]');
    const maximo = Math.min(3, Number(grupo.dataset.maxIntermedias) || 3);
    const tipo = formulario.querySelector('[name="id_tipo_servicio"]');
    const ayudaDirecto = formulario.querySelector('[data-directo-ayuda]');
    const esDirecto = () => tipo?.selectedOptions[0]?.dataset.servicioDirecto === '1';
    let tipoAnterior = tipo?.value;
    const puntosFijos = ['origen', 'destino']
        .map((id) => document.getElementById(id))
        .filter((select) => select instanceof HTMLSelectElement);
    let siguienteId = lista.querySelectorAll('[data-fila-intermedia]').length;

    const filas = () => [...lista.querySelectorAll('[data-fila-intermedia]')];
    const selectores = () => [
        ...puntosFijos,
        ...filas().map((fila) => fila.querySelector('[data-intermedia-select]')),
    ].filter((select) => select instanceof HTMLSelectElement);

    const mostrarError = (mensaje) => {
        error.textContent = mensaje;
        error.hidden = mensaje === '';
    };

    const actualizar = () => {
        const elementos = filas();
        const seleccionados = new Map();
        selectores().forEach((select) => {
            if (select.value !== '') {
                seleccionados.set(select.value, (seleccionados.get(select.value) ?? 0) + 1);
            }
        });

        let hayDuplicados = false;
        selectores().forEach((select) => {
            const duplicado = select.value !== '' && (seleccionados.get(select.value) ?? 0) > 1;
            select.setCustomValidity(duplicado ? 'No repita una parada en el recorrido.' : '');
            if (duplicado) {
                select.setAttribute('aria-invalid', 'true');
                hayDuplicados = true;
            } else {
                select.removeAttribute('aria-invalid');
            }

            const reservados = new Set(selectores()
                .filter((otro) => otro !== select && otro.value !== '')
                .map((otro) => otro.value));
            [...select.options].forEach((opcion) => {
                opcion.disabled = opcion.hasAttribute('data-no-disponible') || (opcion.value !== '' && reservados.has(opcion.value));
            });
        });

        elementos.forEach((fila, indice) => {
            const label = fila.querySelector('label');
            const select = fila.querySelector('[data-intermedia-select]');
            const id = `intermedia-${indice}`;
            label.textContent = `Parada intermedia ${indice + 1}`;
            label.htmlFor = id;
            select.id = id;
            fila.querySelector('[data-subir-intermedia]').disabled = indice === 0;
            fila.querySelector('[data-bajar-intermedia]').disabled = indice === elementos.length - 1;
            fila.querySelector('[data-subir-intermedia]').setAttribute('aria-label', `Subir parada intermedia ${indice + 1}`);
            fila.querySelector('[data-bajar-intermedia]').setAttribute('aria-label', `Bajar parada intermedia ${indice + 1}`);
            fila.querySelector('[data-quitar-intermedia]').setAttribute('aria-label', `Quitar parada intermedia ${indice + 1}`);
        });

        const directo = esDirecto();
        agregar.disabled = directo || elementos.length >= maximo;
        agregar.hidden = directo;
        // No retirar puntos existentes al abrir una ruta antigua: requiere decisión explícita.
        grupo.hidden = directo && elementos.length === 0;
        if (ayudaDirecto) ayudaDirecto.hidden = !directo;
        tipo?.setCustomValidity(directo && elementos.length > 0
            ? 'El servicio directo únicamente permite origen y destino. Quite las paradas intermedias o cambie el tipo de servicio.' : '');
        conteo.textContent = `${elementos.length} de ${maximo} paradas intermedias.`;
        if (directo && elementos.length > 0) {
            mostrarError('El servicio directo únicamente permite origen y destino. Quite las paradas intermedias o cambie el tipo de servicio.');
        } else if (hayDuplicados) {
            mostrarError('No repita paradas entre origen, paradas intermedias y destino.');
        } else if (error.textContent !== '') {
            mostrarError('');
        }
    };

    agregar.addEventListener('click', () => {
        const elementos = filas();
        if (esDirecto() || elementos.length >= maximo) return;
        const fragmento = plantilla.content.cloneNode(true);
        const fila = fragmento.querySelector('[data-fila-intermedia]');
        fila.querySelector('select').id = `intermedia-${siguienteId++}`;
        lista.append(fila);
        actualizar();
        fila.querySelector('select').focus();
    });

    lista.addEventListener('click', (evento) => {
        const boton = evento.target.closest('button');
        if (!boton) return;
        const fila = boton.closest('[data-fila-intermedia]');
        const elementos = filas();
        const indice = elementos.indexOf(fila);
        if (boton.matches('[data-quitar-intermedia]')) {
            const siguiente = elementos[indice + 1] ?? elementos[indice - 1];
            fila.remove();
            actualizar();
            (siguiente?.querySelector('select') ?? agregar).focus();
        } else if (boton.matches('[data-subir-intermedia]') && indice > 0) {
            lista.insertBefore(fila, elementos[indice - 1]);
            actualizar();
            fila.querySelector('select').focus();
        } else if (boton.matches('[data-bajar-intermedia]') && indice < elementos.length - 1) {
            lista.insertBefore(elementos[indice + 1], fila);
            actualizar();
            fila.querySelector('select').focus();
        }
    });

    tipo?.addEventListener('change', () => {
        if (esDirecto() && filas().length > 0) {
            if (!window.confirm('El servicio directo únicamente permite origen y destino. ¿Desea quitar las paradas intermedias del formulario? Los cambios no se guardarán hasta confirmar.')) {
                tipo.value = tipoAnterior;
                actualizar();
                return;
            }
            filas().forEach((fila) => fila.remove());
        }
        tipoAnterior = tipo.value;
        actualizar();
    });
    formulario.addEventListener('change', (evento) => {
        if (evento.target.matches('[data-recorrido-control], [data-intermedia-select]')) actualizar();
    });
    formulario.addEventListener('submit', (evento) => {
        actualizar();
        if (esDirecto() && filas().length > 0) {
            evento.preventDefault();
            tipo.reportValidity();
            return;
        }
        if (filas().filter((fila) => fila.querySelector('select').value !== '').length > maximo) {
            evento.preventDefault();
            mostrarError('Solo se permiten tres paradas intermedias: cinco puntos en total.');
            return;
        }
        if (selectores().some((select) => !select.checkValidity())) {
            evento.preventDefault();
            mostrarError('Corrija las paradas seleccionadas antes de revisar el recorrido.');
        }
    });

    actualizar();
});
