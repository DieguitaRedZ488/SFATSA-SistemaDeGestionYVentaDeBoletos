'use strict';

const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../public/assets/js/usuarios.js'), 'utf8');
let checks = 0;
function verificar(ok) { assert.ok(ok); checks++; }
function campo(value, required = false) {
    return { value, required, eventos: {}, setCustomValidity(m) { this.error = m; }, addEventListener(t, f) { this.eventos[t] = f; } };
}
function escenario(required = false) {
    const capacidad = campo('25', true); const telefono = campo('', required);
    vm.runInNewContext(source, { document: { querySelectorAll: (s) => s === '[data-capacidad-vehiculo]' ? [capacidad] : s === '[data-telefono]' ? [telefono] : [] } });
    return { capacidad, telefono };
}
const { capacidad, telefono } = escenario();
for (const v of ['1', '15', '25', '30']) { capacidad.value = v; capacidad.eventos.input(); verificar(capacidad.error === ''); }
for (const v of ['', '0', '-1', '31', '60', '65535', '15.5', 'abc', '1e1', ' 15']) {
    capacidad.value = v; capacidad.eventos.input(); verificar(capacidad.error === 'La capacidad debe estar entre 1 y 30 pasajeros');
}
verificar(telefono.error === '');
for (const v of ['9513440979', '5512345678', '0012345678']) { telefono.value = v; telefono.eventos.input(); verificar(telefono.error === '' && telefono.value === v); }
for (const v of ['951344097', '95134409799', '951-344-0979', '+529513440979', '951ABC0979', ' ']) {
    telefono.value = v; telefono.eventos.change(); verificar(telefono.error === 'El teléfono debe contener exactamente 10 dígitos' && telefono.value === v);
}
let rechazado = false;
telefono.eventos.beforeinput({ data: '+52', preventDefault() { rechazado = true; } }); verificar(rechazado);
telefono.value = '001234567899'; telefono.eventos.input(); verificar(telefono.value === '0012345678');
telefono.value = '00123abc'; telefono.eventos.input(); verificar(telefono.value === '00123' && telefono.error !== '');
verificar(escenario(true).telefono.error === 'El teléfono debe contener exactamente 10 dígitos');
console.log(`Validaciones de captura JavaScript: ${checks} comprobaciones focalizadas correctas; DOM simulado, sin navegador.`);
