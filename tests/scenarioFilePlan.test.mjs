import test from 'node:test';
import assert from 'node:assert/strict';
import { planScenarioFiles, suggestScenarioNumber } from '../resources/js/components/scenarioFilePlan.js';

const row = (key, values) => ({ key, valido: true, extension: 'docx', sha256: key, numero_informe: null, origen_numero: null, numero_escenario_en_nombre: null, ...values });

test('results take the scenario number and warn when the name says otherwise', () => {
    const rows = [row('r', { tipo: 'resultados', nombre_original: 'Resultdos esc 3.xlsx', extension: 'xlsx', numero_escenario_en_nombre: 3 })];
    const item = planScenarioFiles({ rows, selected: new Set(['r']), scenarioNumber: 1 }).get('r');
    assert.equal(item.destino, 'resultados escenario 1.xlsx');
    assert.equal(item.accion, 'nuevo');
    assert.equal(item.advertencias.length, 1);
});

test('reports keep their number, numberless ones get the next one and unchecked files are ignored', () => {
    const existing = [{ nombre: 'informe escenario 1.docx', tipo: 'informe', numero: 1, extension: 'docx', sha256: 'old' }];
    const rows = [
        row('a', { tipo: 'informe', nombre_original: 'Informe_esc 1.docx', numero_informe: 1, origen_numero: 'nombre' }),
        row('b', { tipo: 'informe', nombre_original: 'Informe final.docx' }),
        row('c', { tipo: 'informe', nombre_original: 'Anexo.docx' }),
        row('d', { tipo: 'informe', nombre_original: 'Informe esc 4.docx', numero_informe: 4, origen_numero: 'nombre' }),
    ];
    let plan = planScenarioFiles({ rows, selected: new Set(['a', 'b', 'c', 'd']), existing });
    assert.equal(plan.get('a').destino, 'informe escenario 1.docx');
    assert.equal(plan.get('a').accion, 'reemplaza');
    assert.equal(plan.get('b').destino, 'informe escenario 5.docx');
    assert.equal(plan.get('c').destino, 'informe escenario 6.docx');
    plan = planScenarioFiles({ rows, selected: new Set(['c']), existing });
    assert.equal(plan.get('c').destino, 'informe escenario 2.docx');
    assert.equal(plan.has('b'), false);
});

test('identical content is reported as unchanged and conflicts match the server rules', () => {
    const existing = [{ nombre: 'informe escenario 1.docx', tipo: 'informe', numero: 1, extension: 'docx', sha256: 'same' }];
    const rows = [
        row('same', { tipo: 'informe', nombre_original: 'copia.docx', numero_informe: 1, origen_numero: 'contenido' }),
        row('doc', { tipo: 'informe', nombre_original: 'informe 1.doc', numero_informe: 1, extension: 'doc' }),
        row('r1', { tipo: 'resultados', nombre_original: 'a.xlsx', extension: 'xlsx' }),
        row('r2', { tipo: 'resultados', nombre_original: 'b.xlsx', extension: 'xlsx' }),
    ];
    const plan = planScenarioFiles({ rows, selected: new Set(['same', 'doc', 'r1', 'r2']), scenarioNumber: 2, existing });
    assert.equal(plan.get('same').accion, 'sin_cambios');
    assert.match(plan.get('doc').conflictos[0], /igual que «copia\.docx»/);
    // Mismo número en otro formato: reemplaza (no es conflicto).
    const alone = planScenarioFiles({ rows, selected: new Set(['doc']), existing }).get('doc');
    assert.equal(alone.conflictos.length, 0);
    assert.equal(alone.accion, 'reemplaza');
    assert.equal(plan.get('r1').conflictos.length, 0);
    assert.match(plan.get('r2').conflictos[0], /otro libro de resultados/);
});

test('scenario number suggestion prefers the free number in the file name', () => {
    assert.equal(suggestScenarioNumber(4, [1, 2]), 4);
    assert.equal(suggestScenarioNumber(2, [1, 2]), 3);
    assert.equal(suggestScenarioNumber(null, [2, 3]), 1);
    assert.equal(suggestScenarioNumber(null, []), 1);
});

test('the same number with another extension in one upload is a conflict', () => {
    const rows = [
        row('a', { tipo: 'informe', nombre_original: 'informe 2.doc', numero_informe: 2, extension: 'doc' }),
        row('b', { tipo: 'informe', nombre_original: 'informe 2.docx', numero_informe: 2, extension: 'docx' }),
    ];
    const plan = planScenarioFiles({ rows, selected: new Set(['a', 'b']) });
    assert.equal(plan.get('a').conflictos.length, 0);
    assert.match(plan.get('b').conflictos[0], /informe 2/);
});

test('a results workbook replaces one saved with an old name in the current version', () => {
    const existing = [
        { nombre: 'Resultados caso 1.xlsx', tipo: 'documento', numero: null, extension: 'xlsx', version: '1.1', sha256: 'old', resultados_anterior: true },
    ];
    const rows = [row('r', { tipo: 'resultados', nombre_original: 'nuevo.xlsx', extension: 'xlsx' })];
    assert.equal(planScenarioFiles({ rows, selected: new Set(['r']), scenarioNumber: 1, existing }).get('r').accion, 'reemplaza');
    const older = [{ ...existing[0], version: '1.0' }, { nombre: 'otro.docx', tipo: 'documento', numero: null, extension: 'docx', version: '1.1', sha256: 'x' }];
    assert.equal(planScenarioFiles({ rows, selected: new Set(['r']), scenarioNumber: 1, existing: older }).get('r').accion, 'nuevo');
});

test('an existing report is only updated by a newer file', () => {
    const existing = [{ nombre: 'informe escenario 1.pdf', tipo: 'informe', numero: 1, extension: 'pdf', version: '1.0', sha256: 'old', fecha_archivo: 1000 }];
    const report = (fecha) => [row('r', { tipo: 'informe', nombre_original: 'Informe 1.pdf', extension: 'pdf', numero_informe: 1, fecha })];
    assert.equal(planScenarioFiles({ rows: report(900), selected: new Set(['r']), existing }).get('r').accion, 'omitido');
    assert.equal(planScenarioFiles({ rows: report(1000), selected: new Set(['r']), existing }).get('r').accion, 'omitido');
    assert.equal(planScenarioFiles({ rows: report(1001), selected: new Set(['r']), existing }).get('r').accion, 'reemplaza');
    assert.equal(planScenarioFiles({ rows: report(null), selected: new Set(['r']), existing }).get('r').accion, 'reemplaza');
});
