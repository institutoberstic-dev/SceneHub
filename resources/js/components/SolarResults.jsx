import { useEffect, useMemo, useState } from 'react';
import { api, errorMessage } from '../http';
import { detectChanges, formatTime, numeric, variables } from './solarSampling';
import Pagination from './Pagination.jsx';

const labels = { inicio: 'Inicio de generación', fin: 'Fin de generación', deficit: 'Entrada en déficit', excedente: 'Entrada en excedente', variacion: 'Variación' };
const defaults = () => ({ variable: 'potencia_solar_w', from: '', to: '', threshold: '0', event: '', changesOnly: false, page: 0, pageSize: 15 });
const number = value => numeric(value) === null ? '—' : Number(value).toLocaleString('es-CO', { maximumFractionDigits: 4 });

export default function SolarResults() {
    const [scenarios, setScenarios] = useState([]);
    const [scenario, setScenario] = useState('');
    const [files, setFiles] = useState([]);
    const [file, setFile] = useState('');
    const [interval, setInterval] = useState(1);
    const [filters, setFilters] = useState({ 1: defaults(), 5: defaults(), 10: defaults(), 60: defaults() });
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [reload, setReload] = useState(0);
    const filter = filters[interval];
    const update = patch => setFilters(current => ({ ...current, [interval]: { ...current[interval], page: 0, ...patch } }));

    useEffect(() => {
        const controller = new AbortController();
        api.get('/escenarios-data', { signal: controller.signal }).then(({ data }) => {
            setScenarios(data);
        }).catch(e => { if (!controller.signal.aborted) setError(errorMessage(e)); });
        return () => controller.abort();
    }, [reload]);

    useEffect(() => {
        const controller = new AbortController();
        setFiles([]); setFile(''); setError('');
        setFilters({ 1: defaults(), 5: defaults(), 10: defaults(), 60: defaults() });
        if (!scenario) { setLoading(false); return () => controller.abort(); }
        setLoading(true);
        api.get(`/escenarios-data/${scenario}/resultados-solares`, { signal: controller.signal }).then(({ data }) => {
            if (!Array.isArray(data.archivos)) throw new Error('Formato de respuesta inesperado.');
            setFiles(data.archivos);
            setFile(String(data.archivos[0]?.id ?? ''));
        }).catch(e => {
            if (controller.signal.aborted) return;
            setError(errorMessage(e, 'No fue posible consultar los resultados solares. Comprueba el acceso al escenario e inténtalo de nuevo.'));
        }).finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [scenario, reload]);

    const selected = files.find(item => String(item.id) === file);
    const rows = selected?.muestreos?.[interval] ?? [];
    const threshold = numeric(filter.threshold);
    const invalid = threshold === null || threshold < 0 || (filter.from !== '' && (numeric(filter.from) === null || Number(filter.from) < 0)) || (filter.to !== '' && (numeric(filter.to) === null || Number(filter.to) < 0)) || (filter.from !== '' && filter.to !== '' && Number(filter.from) > Number(filter.to));
    const analyzed = useMemo(() => detectChanges(rows, filter.variable, threshold ?? 0, interval), [rows, filter.variable, threshold, interval]);
    const visible = analyzed.filter(row => !invalid && (filter.from === '' || row.tiempo_minutos >= Number(filter.from)) && (filter.to === '' || row.tiempo_minutos <= Number(filter.to)) && (!filter.changesOnly || row.change) && (!filter.event || row.change === filter.event));
    const [, title, unit] = variables.find(v => v[0] === filter.variable);
    const minuteStart = detectChanges(selected?.muestreos?.[1] ?? [], 'potencia_solar_w', threshold ?? 0, 1).find(row => row.change === 'inicio');
    const pageCount = Math.max(1, Math.ceil(visible.length / filter.pageSize));
    const page = Math.min(filter.page, pageCount - 1);
    const plotted = visible.filter(row => row.value !== null);
    const xMin = Number(visible[0]?.tiempo_minutos ?? 0), xMax = Number(visible.at(-1)?.tiempo_minutos ?? 1);
    const yMin = plotted.length ? Math.min(...plotted.map(row => row.value)) : 0;
    const yMax = plotted.length ? Math.max(...plotted.map(row => row.value)) : 1;

    return <section className="content-card solar-results">
        <div className="section-heading section-heading--inside"><div><h2>Simulación solar</h2><p>Explora cada hoja y los cambios observados en su intervalo.</p></div><button type="button" className="secondary-button" onClick={() => setReload(value => value + 1)}>Actualizar</button></div>
        <div className="solar-filters">
            <label>Escenario<select className="text-input" value={scenario} onChange={e => setScenario(e.target.value)}><option value="">Selecciona un escenario</option>{scenarios.map(item => <option key={item.id} value={item.id}>{item.nombre}{item.version_datos ? ` (v${item.version_datos})` : ''}</option>)}</select></label>
            <label>Archivo<select className="text-input" disabled={!files.length || loading} value={file} onChange={e => { setFile(e.target.value); setFilters({ 1: defaults(), 5: defaults(), 10: defaults(), 60: defaults() }); }}><option value="">Sin archivo seleccionado</option>{files.map(item => <option key={item.id} value={item.id}>{item.nombre}{item.version_datos ? ` (v${item.version_datos})` : ''}</option>)}</select></label>
        </div>
        <div className="detail-tabs solar-tabs" aria-label="Hojas de muestreo">{[1, 5, 10, 60].map(value => <button type="button" key={value} aria-pressed={interval === value} className={interval === value ? 'active' : ''} onClick={() => setInterval(value)}>{value === 1 ? 'Por minuto' : value === 60 ? 'Por hora' : `Cada ${value} minutos`}</button>)}</div>
        <div className="solar-body">
            <div className="solar-filters">
                <label>Variable<select className="text-input" value={filter.variable} onChange={e => update({ variable: e.target.value, event: '', threshold: '0' })}>{variables.map(([key, text, suffix]) => <option key={key} value={key}>{text} ({suffix})</option>)}</select></label>
                <label>Desde el minuto<input className="text-input" type="number" min="0" step="1" value={filter.from} onChange={e => update({ from: e.target.value })} placeholder="Inicio" /></label>
                <label>Hasta el minuto<input className="text-input" type="number" min="0" step="1" value={filter.to} onChange={e => update({ to: e.target.value })} placeholder="Final" /></label>
                <label>Umbral ({unit})<input className="text-input" type="number" min="0" step="any" value={filter.threshold} onChange={e => update({ threshold: e.target.value })} /></label>
                <label>Tipo de cambio<select className="text-input" value={filter.event} onChange={e => update({ event: e.target.value })}><option value="">Todos</option><option value="variacion">Variación</option>{(filter.variable === 'potencia_solar_w' ? ['inicio', 'fin'] : filter.variable === 'potencia_neta_w' ? ['deficit', 'excedente'] : []).map(key => <option key={key} value={key}>{labels[key]}</option>)}</select></label>
            </div>
            <div className="solar-actions"><label><input type="checkbox" checked={filter.changesOnly} onChange={e => update({ changesOnly: e.target.checked })} /> Solo cambios importantes</label><button type="button" className="secondary-button" onClick={() => update(defaults())}>Limpiar filtros</button></div>
            <p className="muted-cell">Tiempo transcurrido en HH:MM, incluso después de 24 horas. Umbral 0 muestra cualquier variación; ajústalo para excluir fluctuaciones pequeñas. Los acumulados muestran incrementos, no producción instantánea.</p>
            {invalid && <p role="alert">Revisa el rango de minutos y el umbral: deben ser números no negativos y el inicio no puede superar el final.</p>}
            {error && <p role="alert">{error}</p>}
            {selected?.advertencias?.map(message => <p className="info-banner" key={message}>{message}</p>)}
            <div aria-live="polite">{loading ? <p>Cargando resultados…</p> : error || invalid ? null : !scenario ? <p className="solar-empty">Selecciona un escenario para consultar sus resultados.</p> : !selected ? <p className="solar-empty">Este escenario no tiene archivos de resultados solares disponibles.</p> : !rows.length ? <p className="solar-empty">No hay registros para esta hoja.</p> : !visible.length ? <p className="solar-empty">No hay registros que coincidan con los filtros.</p> : <>
                {filter.variable === 'potencia_solar_w' && minuteStart && <p className="info-banner">Primer inicio observado en la hoja Minutos: {formatTime(minuteStart.tiempo_minutos)} (umbral: {number(threshold)} W).</p>}
                <div className="solar-chart"><strong>{title} ({unit})</strong><p>{number(yMin)} a {number(yMax)} {unit} · {formatTime(xMin)} a {formatTime(xMax)}</p><svg viewBox="0 0 900 200" role="img" aria-label={`${title}: ${plotted.length} muestras. Los puntos destacados indican cambios.`}><line x1="20" y1="180" x2="880" y2="180" stroke="#ccd5e1" />{plotted.map(row => <circle key={row.tiempo_minutos} cx={20 + (Number(row.tiempo_minutos) - xMin) / (xMax - xMin || 1) * 860} cy={180 - (row.value - yMin) / (yMax - yMin || 1) * 160} r={row.change ? 3.5 : 2} fill={row.change ? '#b45309' : '#175cd3'}><title>{formatTime(row.tiempo_minutos)}: {number(row.value)} {unit}{row.change ? ` · ${labels[row.change]}` : ''}</title></circle>)}</svg><small>Azul: muestra · Ámbar: cambio detectado. Valores ausentes no se representan.</small></div>
                <p>{visible.length} registros · {visible.filter(row => row.change).length} cambios. El valor anterior corresponde al registro previo de esta hoja.</p>
                <div className="solar-table"><table><thead><tr><th>Tiempo (HH:MM)</th><th>Minuto</th><th>{title} ({unit})</th><th>Anterior ({unit})</th><th>Cambio ({unit})</th><th>Observación</th></tr></thead><tbody className="page-fade" key={`${page}-${filter.pageSize}-${interval}`}>{visible.slice(page * filter.pageSize, (page + 1) * filter.pageSize).map(row => <tr key={row.tiempo_minutos}><td>{formatTime(row.tiempo_minutos)}</td><td>{row.tiempo_minutos}</td><td>{number(row.value)}</td><td>{number(row.before)}{row.before !== null && <small> en {formatTime(row.previousTime)}</small>}</td><td>{row.value !== null && row.before !== null ? number(row.value - row.before) : '—'}</td><td>{labels[row.change] || 'Sin transición detectada'}</td></tr>)}</tbody></table></div>
                <Pagination total={visible.length} page={page + 1} pageSize={filter.pageSize} onPageChange={value => update({ page: value - 1 })} onPageSizeChange={value => update({ pageSize: value })} />
            </>}</div>
        </div>
    </section>;
}
