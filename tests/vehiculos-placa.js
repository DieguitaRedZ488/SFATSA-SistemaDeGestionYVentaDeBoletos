'use strict';

// DOM mínimo en memoria: prueba el script real, sin navegador ni dependencias externas.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../public/assets/js/vehiculos-placa.js'), 'utf8');
let checks = 0;
function comprobar(value) { assert.ok(value); checks++; }
function nodo(value = '') {
    return { value, hidden: true, disabled: true, required: false, eventos: {}, atributos: {},
        addEventListener(tipo, fn) { this.eventos[tipo] = fn; },
        setCustomValidity(mensaje) { this.error = mensaje; },
        setAttribute(k, v) { this.atributos[k] = v; }, removeAttribute(k) { delete this.atributos[k]; },
        checkValidity() { return !this.error && (!this.required || this.value !== ''); }, focus() { this.enfocado = true; } };
}
function captura(partes = ['', '', '']) {
    const grupos = partes.map(nodo); const form = nodo(); const completa = nodo(partes.join('-'));
    if (partes.every((p) => p === '')) completa.value = '';
    completa.name = 'placa'; completa.form = form;
    const contador = nodo(); const mensaje = nodo(); const fallback = nodo(); const area = nodo();
    const selectores = { '[name="placa"]': completa, '[data-placa-conteo]': contador, '[data-placa-mensaje]': mensaje,
        '[data-placa-fallback]': fallback, '[data-placa-grupos]': area };
    const root = { querySelector: (selector) => selectores[selector], querySelectorAll: () => grupos };
    vm.runInNewContext(source, { document: { querySelectorAll: () => [root] }, setTimeout: (fn) => fn() });
    return { grupos, form, completa, contador, mensaje, area, escribir(i, valor) { grupos[i].value = valor; grupos[i].eventos.input(); } };
}
const c = captura();
comprobar(c.completa.name === 'placa' && c.completa.type === 'hidden' && c.area.hidden === false);
comprobar(c.grupos.every((g) => !g.disabled && g.required));
c.escribir(0, 'aBc'); c.escribir(1, '123'); c.escribir(2, 'a');
comprobar(c.completa.value === 'ABC-123-A' && c.contador.textContent.startsWith('7/7'));
comprobar(c.grupos.every((g) => g.checkValidity()));
c.escribir(2, 'a99'); comprobar(c.grupos[2].value === 'A' && c.completa.value === 'ABC-123-A');
c.escribir(0, 'aB@C ñ'); comprobar(c.grupos[0].value === 'ABC');
c.escribir(1, ''); comprobar(c.mensaje.textContent.includes('tres grupos') && !c.grupos[1].checkValidity());
let cancelado = false; c.form.eventos.submit({ preventDefault() { cancelado = true; } }); comprobar(cancelado);
c.escribir(1, '12'); comprobar(c.contador.textContent.startsWith('6/7') && c.mensaje.textContent.includes('exactamente 7'));
for (const partes of [['AB', '12', '345'], ['A', '12345', 'B'], ['ABC', '12', '34'], ['12', 'ABC', '34']]) {
    const f = captura(partes); comprobar(f.completa.value === partes.join('-') && f.grupos.every((g) => g.checkValidity()));
}
const minusculas = captura(['a', '12345', 'b']); comprobar(minusculas.completa.value === 'A-12345-B');
const legado = captura(['ABC', '123', '45']); comprobar(legado.grupos[2].value === '45' && legado.contador.textContent.startsWith('8/7'));
comprobar(!legado.grupos[0].checkValidity()); legado.escribir(2, '4'); comprobar(legado.completa.value === 'ABC-123-4' && legado.grupos.every((g) => g.checkValidity()));
const simbolo = captura(['ABC', '12', '@A']); comprobar(simbolo.mensaje.textContent.includes('únicamente'));
simbolo.escribir(2, 'ß@a1'); comprobar(simbolo.grupos[2].value === 'A1' && simbolo.completa.value === 'ABC-12-A1');
console.log(`Placas HU-02 JavaScript: ${checks} comprobaciones focalizadas correctas; DOM simulado, sin navegador.`);
