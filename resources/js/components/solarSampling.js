export const variables = [
    ['potencia_solar_w', 'Potencia solar', 'W'],
    ['potencia_neta_w', 'Potencia neta', 'W'],
    ['potencia_consumida_planta_w', 'Consumo de planta', 'W'],
    ['energia_almacenada_wh', 'Energía almacenada', 'Wh'],
    ['irradiancia_w_m2', 'Radiación solar', 'W/m²'],
    ['temperatura_c', 'Temperatura', '°C'],
    ['velocidad_viento_m_s', 'Velocidad del viento', 'm/s'],
    ['caudal_m3_h', 'Caudal', 'm³/h'],
    ['agua_desalinizada_acum_m3', 'Agua desalinizada acumulada', 'm³'],
    ['salmuera_acum_m3', 'Salmuera acumulada', 'm³'],
    ['lodos_gruesos_acum_paquetes', 'Lodos gruesos acumulados', 'paquetes'],
    ['lodos_finos_acum_paquetes', 'Lodos finos acumulados', 'paquetes'],
    // Formato extendido: balance de batería y respaldo diésel.
    ['estado_carga_pct', 'Estado de carga de la batería', '%'],
    ['excedente_no_aprovechado_acum_wh', 'Excedente no aprovechado acumulado', 'Wh'],
    ['energia_diesel_acum_wh', 'Energía entregada por el diésel acumulada', 'Wh'],
    ['combustible_diesel_acum_l', 'Combustible diésel acumulado', 'L'],
    ['demanda_no_cubierta_acum_wh', 'Demanda no cubierta acumulada', 'Wh'],
];

/** Variables con datos en la versión seleccionada; las versiones antiguas no informan la lista. */
export function availableVariables(version) {
    const keys = version?.variables_disponibles;
    if (!Array.isArray(keys) || keys.length === 0) return variables;
    const filtered = variables.filter(([key]) => keys.includes(key));
    return filtered.length ? filtered : variables;
}

export const numeric = value => value !== null && value !== '' && value !== undefined && Number.isFinite(Number(value)) ? Number(value) : null;
export function formatTime(minutes) {
    const value = numeric(minutes);
    if (value === null) return '—';
    return `${Math.floor(value / 60).toString().padStart(2, '0')}:${Math.floor(value % 60).toString().padStart(2, '0')}`;
}

// Detect before filtering by time, so the first visible row retains its predecessor.
export function detectChanges(rows, variable, threshold = 0, interval = 1) {
    const sorted = [...rows].filter(row => numeric(row.tiempo_minutos) !== null)
        .sort((a, b) => Number(a.tiempo_minutos) - Number(b.tiempo_minutos));
    return sorted.map((row, index) => {
        const previous = sorted[index - 1];
        const value = numeric(row[variable]);
        const before = numeric(previous?.[variable]);
        let change = null;
        const continuous = previous && Number(row.tiempo_minutos) - Number(previous.tiempo_minutos) === interval;
        if (continuous && value !== null && before !== null) {
            if (variable === 'potencia_solar_w') {
                if (before <= threshold && value > threshold) change = 'inicio';
                else if (before > threshold && value <= threshold) change = 'fin';
            }
            if (variable === 'potencia_neta_w') {
                if (before >= 0 && value < 0) change = 'deficit';
                else if (before <= 0 && value > 0) change = 'excedente';
            }
            if (!change && Math.abs(value - before) > threshold) change = 'variacion';
        }
        return { ...row, value, before, change, previousTime: previous?.tiempo_minutos };
    });
}
