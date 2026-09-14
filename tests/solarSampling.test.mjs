import test from 'node:test';
import assert from 'node:assert/strict';
import { detectChanges, formatTime } from '../resources/js/components/solarSampling.js';

test('detects solar start using a threshold and keeps the predecessor', () => {
    const result = detectChanges([{ tiempo_minutos: 13, potencia_solar_w: 12 }, { tiempo_minutos: 12, potencia_solar_w: 0 }], 'potencia_solar_w', 1);
    assert.equal(result[1].change, 'inicio');
    assert.equal(result[1].previousTime, 12);
    assert.equal(result[0].change, null);
});
test('does not infer transitions across missing values or sampling gaps', () => {
    for (const rows of [
        [{ tiempo_minutos: 1, potencia_solar_w: null }, { tiempo_minutos: 2, potencia_solar_w: 20 }],
        [{ tiempo_minutos: 1, potencia_solar_w: 0 }, { tiempo_minutos: 3, potencia_solar_w: 20 }],
    ]) assert.equal(detectChanges(rows, 'potencia_solar_w')[1].change, null);
});
test('respects five minute samples and reports end of generation', () => {
    const rows = [{ tiempo_minutos: 5, potencia_solar_w: 20 }, { tiempo_minutos: 10, potencia_solar_w: 0 }];
    assert.equal(detectChanges(rows, 'potencia_solar_w', 1, 5)[1].change, 'fin');
});
test('detects deficit and excludes variations at the threshold', () => {
    assert.equal(detectChanges([{ tiempo_minutos: 1, potencia_neta_w: 1 }, { tiempo_minutos: 2, potencia_neta_w: -1 }], 'potencia_neta_w')[1].change, 'deficit');
    assert.equal(detectChanges([{ tiempo_minutos: 1, temperatura_c: 20 }, { tiempo_minutos: 2, temperatura_c: 21 }], 'temperatura_c', 1)[1].change, null);
});
test('formats elapsed hours without wrapping after 24 hours', () => {
    assert.equal(formatTime(1805), '30:05');
    assert.equal(formatTime(null), '—');
});
