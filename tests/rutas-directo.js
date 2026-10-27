'use strict';

// DOM simulado: ejecuta el script real, sin navegador ni conexiones a MariaDB.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
let checks = 0;
const comprobar = (ok) => { assert.ok(ok); checks++; };
class Control {
    constructor() { this.eventos = {}; this.dataset = {}; this.hidden = false; this.disabled = false; }
    addEventListener(nombre, accion) { this.eventos[nombre] = accion; }
    setAttribute() {}
    removeAttribute() {}
    focus() {}
    matches() { return true; }
    emitir(nombre, datos = {}) { this.eventos[nombre]?.(datos); }
}
class Select extends Control {
    constructor(valor, tipo = false) {
        super(); this.value = valor; this.error = '';
        this.options = tipo ? ['1', '2'].map((id) => ({ value: id, dataset: { servicioDirecto: id === '1' ? '1' : '0' } }))
            : ['', '1', '2', '3', '4', '5', '6'].map((id) => ({ value: id, hasAttribute: () => false }));
    }
    get selectedOptions() { return this.options.filter((o) => o.value === this.value); }
    setCustomValidity(error) { this.error = error; }
    checkValidity() { return !this.error; }
    reportValidity() { return this.checkValidity(); }
}
function entorno(tipoInicial, ids = []) {
    const form = new Control(), grupo = new Control(), lista = new Control(); lista.filas = [];
    const tipo = new Select(tipoInicial, true), origen = new Select('1'), destino = new Select('2');
    const agregar = new Control(), ayuda = new Control(), error = new Control(), conteo = new Control();
    error.textContent = ''; grupo.dataset.maxIntermedias = '3';
    const fila = (id = '') => {
        const control = new Select(id), label = {}, botones = [new Control(), new Control(), new Control()];
        const nodo = {
            querySelector: (s) => s === 'label' ? label : s.includes('subir') ? botones[0] : s.includes('bajar') ? botones[1] : s.includes('quitar') ? botones[2] : control,
            remove: () => { lista.filas = lista.filas.filter((f) => f !== nodo); },
        };
        return nodo;
    };
    lista.filas = ids.map(fila);
    lista.querySelectorAll = () => lista.filas;
    lista.append = (nodo) => lista.filas.push(nodo);
    const plantilla = { content: { cloneNode: () => ({ querySelector: () => fila() }) } };
    const controles = { '[data-lista-intermedias]': lista, '[data-plantilla-intermedia]': plantilla,
        '[data-agregar-intermedia]': agregar, '[data-conteo-intermedias]': conteo, '[data-error-intermedias]': error };
    grupo.querySelector = (s) => controles[s]; grupo.closest = () => form;
    form.querySelector = (s) => s === '[data-directo-ayuda]' ? ayuda : tipo;
    const estado = { acepta: false, preguntas: 0 };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../public/assets/js/rutas.js'), 'utf8'), {
        document: { querySelectorAll: () => [grupo], getElementById: (id) => id === 'origen' ? origen : destino },
        HTMLSelectElement: Select,
        window: { confirm: () => { estado.preguntas++; return estado.acepta; } },
    });
    return { form, grupo, lista, tipo, origen, destino, agregar, ayuda, error, conteo, estado,
        cambiar: (valor) => { tipo.value = valor; tipo.emitir('change'); } };
}
const f = entorno('2', ['3', '4']);
comprobar(!f.agregar.disabled && !f.agregar.hidden && f.ayuda.hidden);
f.cambiar('1');
comprobar(f.tipo.value === '2' && f.lista.filas.length === 2 && f.estado.preguntas === 1);
comprobar(f.lista.filas[0].querySelector('select').value === '3' && f.lista.filas[1].querySelector('select').value === '4');
f.estado.acepta = true; f.cambiar('1');
comprobar(f.tipo.value === '1' && f.lista.filas.length === 0 && f.estado.preguntas === 2);
comprobar(f.agregar.hidden && f.agregar.disabled && f.grupo.hidden && !f.ayuda.hidden);
f.agregar.emitir('click'); comprobar(f.lista.filas.length === 0);
f.cambiar('2'); comprobar(!f.agregar.hidden && !f.agregar.disabled && !f.grupo.hidden && f.ayuda.hidden);
for (let i = 0; i < 3; i++) f.agregar.emitir('click');
comprobar(f.lista.filas.length === 3 && f.agregar.disabled);
f.agregar.emitir('click'); comprobar(f.lista.filas.length === 3);
f.lista.filas[0].querySelector('select').value = '1';
f.form.emitir('change', { target: f.origen });
comprobar(!f.origen.checkValidity() && !f.error.hidden);
let bloqueado = false;
f.form.emitir('submit', { preventDefault: () => { bloqueado = true; } }); comprobar(bloqueado);
// Un recorrido DIRECTO antiguo no se recorta ni elimina al abrir la edición.
const legado = entorno('1', ['3']);
comprobar(legado.lista.filas.length === 1 && legado.estado.preguntas === 0 && !legado.grupo.hidden);
comprobar(legado.agregar.disabled && legado.agregar.hidden && !legado.tipo.checkValidity());
bloqueado = false;
legado.form.emitir('submit', { preventDefault: () => { bloqueado = true; } }); comprobar(bloqueado);
legado.cambiar('2'); comprobar(legado.lista.filas.length === 1 && legado.tipo.checkValidity());
const directo = entorno('1');
comprobar(directo.grupo.hidden && directo.tipo.checkValidity() && !directo.ayuda.hidden);
console.log(`Rutas DIRECTO: ${checks} comprobaciones correctas con DOM simulado; sin navegador ni MariaDB.`);
