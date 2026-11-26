'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
let checks = 0;
const verificar = (valor) => { assert.ok(valor); checks++; };
const option = (value, ruta, tipo) => ({ value, hidden: false, disabled: false, dataset: { ...(ruta ? { ruta } : {}), ...(tipo ? { tipo } : {}) } });
const select = (options, value = '') => ({
    value, options, eventos: {},
    get selectedOptions() { return this.options.filter((o) => o.value === this.value); },
    addEventListener(nombre, accion) { this.eventos[nombre] = accion; },
});
const ruta = select([option(''), option('1', '1', '1'), option('2', '2', '2')], '1');
const tipo = select([option(''), option('1'), option('2')], '1');
const origen = select([option(''), option('11', '1'), option('21', '2')], '11');
const destino = select([option(''), option('12', '1'), option('22', '2')], '12');
const controles = { id_ruta: ruta, id_tipo_servicio: tipo, id_origen_ruta_parada: origen, id_destino_ruta_parada: destino };
const form = { querySelector: (selector) => controles[selector.match(/name="([^"]+)"/)[1]] || null };
const source = fs.readFileSync(__dirname + '/../public/assets/js/operacion-captura.js', 'utf8');
vm.runInNewContext(source, { document: { querySelectorAll: () => [form] } });
verificar(!origen.options[1].disabled && origen.options[2].disabled);
verificar(!destino.options[1].hidden && destino.options[2].hidden);
verificar(origen.value === '11' && tipo.value === '1');
ruta.value = '2'; ruta.eventos.change();
verificar(tipo.value === '2');
verificar(origen.value === '' && destino.value === '');
verificar(origen.options[1].disabled && !origen.options[2].disabled);
origen.value = '21'; destino.value = '22'; ruta.value = ''; ruta.eventos.change();
verificar(tipo.value === '');
verificar(origen.options.every((o) => !o.disabled) && destino.options.every((o) => !o.hidden));
vm.runInNewContext(source, { document: { querySelectorAll: () => [{ querySelector: () => null }] } });
verificar(true);
console.log(`Captura operativa: ${checks} comprobaciones correctas; selectores de ruta, servicio y paradas con DOM simulado.`);
