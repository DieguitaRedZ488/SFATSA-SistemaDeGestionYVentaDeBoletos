'use strict';
// Mejora progresiva: sin JS todos los selectores siguen utilizables y el servidor valida.
document.querySelectorAll('[data-operacion-captura]').forEach((form) => {
    const ruta = form.querySelector('[name="id_ruta"]');
    const tipo = form.querySelector('[name="id_tipo_servicio"]');
    if (!ruta || !tipo) return;
    const filtrar = (cambio) => {
        const seleccion = ruta.selectedOptions[0];
        if (cambio) tipo.value = seleccion?.dataset.tipo || '';
        ['id_origen_ruta_parada', 'id_destino_ruta_parada'].forEach((campo) => {
            const select = form.querySelector(`[name="${campo}"]`);
            if (!select) return;
            [...select.options].forEach((option) => {
                if (!option.value || !option.dataset.ruta) return;
                const ajena = Boolean(ruta.value) && option.dataset.ruta !== ruta.value;
                option.hidden = ajena;
                option.disabled = ajena;
            });
            if (cambio && select.selectedOptions[0]?.disabled) select.value = '';
        });
    };
    ruta.addEventListener('change', () => filtrar(true));
    filtrar(false);
});
