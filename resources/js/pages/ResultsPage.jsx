import { Activity, BarChart3, BatteryCharging, Droplets, Gauge, Info, RefreshCw, Sun, ThermometerSun, Zap } from 'lucide-react';
import { useEffect, useState } from 'react';
import { api, errorMessage } from '../http';

const solarColumns = [
    ['radiacion', 'Radiación'], ['generacion', 'Generación'], ['voltaje', 'Voltaje'], ['corriente', 'Corriente'], ['bateria', 'Batería'], ['estado', 'Estado'],
];
const hydrogenColumns = [
    ['corriente', 'Corriente'], ['temperatura', 'Temperatura'], ['presion', 'Presión'], ['eficiencia', 'Eficiencia'], ['produccionHidrogeno', 'Hidrógeno'], ['numCeldas', 'Celdas'],
];

export default function ResultsPage() {
    const [tab, setTab] = useState('solar');
    const [solar, setSolar] = useState([]);
    const [hydrogen, setHydrogen] = useState([]);
    const [loading, setLoading] = useState(true);
    const [message, setMessage] = useState('');

    const load = async () => {
        setLoading(true);
        setMessage('');
        try {
            const [solarResponse, hydrogenResponse] = await Promise.all([api.get('/api/simu-solars'), api.get('/api/features')]);
            setSolar(solarResponse.data);
            setHydrogen(hydrogenResponse.data);
        } catch (error) {
            setMessage(errorMessage(error, 'No fue posible consultar la telemetría.'));
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { load(); }, []);

    const data = tab === 'solar' ? solar : hydrogen;
    const columns = tab === 'solar' ? solarColumns : hydrogenColumns;
    const latest = data[0] || {};

    return (
        <div className="page-stack">
            <section className="page-heading"><div><span className="eyebrow">Monitoreo técnico</span><h1>Resultados de simulación</h1><p>Lecturas recibidas por los endpoints de simulación solar y producción de hidrógeno.</p></div><button className="secondary-button" type="button" onClick={load}><RefreshCw size={17} /> Actualizar datos</button></section>
            {message && <div className="alert alert--error"><Info size={18} />{message}</div>}
            <div className="tabs"><button className={tab === 'solar' ? 'active' : ''} onClick={() => setTab('solar')}><Sun size={17} /> Simulación solar <span>{solar.length}</span></button><button className={tab === 'hydrogen' ? 'active' : ''} onClick={() => setTab('hydrogen')}><Droplets size={17} /> Electrolizador <span>{hydrogen.length}</span></button></div>

            {tab === 'solar' ? (
                <section className="metric-grid">
                    <article className="metric-card"><span className="metric-icon metric-icon--orange"><Sun size={21} /></span><div><small>Radiación</small><strong>{latest.radiacion ?? '—'}</strong><p>Última lectura</p></div></article>
                    <article className="metric-card"><span className="metric-icon metric-icon--blue"><Zap size={21} /></span><div><small>Generación</small><strong>{latest.generacion ?? '—'}</strong><p>Producción estimada</p></div></article>
                    <article className="metric-card"><span className="metric-icon metric-icon--green"><BatteryCharging size={21} /></span><div><small>Batería</small><strong>{latest.bateria ?? '—'}</strong><p>Nivel reportado</p></div></article>
                    <article className="metric-card"><span className="metric-icon metric-icon--purple"><ThermometerSun size={21} /></span><div><small>Temperatura</small><strong>{latest.temperatura ?? '—'}</strong><p>Condición actual</p></div></article>
                </section>
            ) : (
                <section className="metric-grid">
                    <article className="metric-card"><span className="metric-icon metric-icon--cyan"><Droplets size={21} /></span><div><small>Producción H₂</small><strong>{latest.produccionHidrogeno ?? '—'}</strong><p>Última lectura</p></div></article>
                    <article className="metric-card"><span className="metric-icon metric-icon--blue"><Activity size={21} /></span><div><small>Corriente</small><strong>{latest.corriente ?? '—'}</strong><p>Sistema PEM</p></div></article>
                    <article className="metric-card"><span className="metric-icon metric-icon--green"><Gauge size={21} /></span><div><small>Eficiencia</small><strong>{latest.eficiencia ?? '—'}</strong><p>Rendimiento</p></div></article>
                    <article className="metric-card"><span className="metric-icon metric-icon--purple"><BarChart3 size={21} /></span><div><small>Celdas</small><strong>{latest.numCeldas ?? '—'}</strong><p>Configuración recibida</p></div></article>
                </section>
            )}

            <section className="content-card">
                <div className="section-heading section-heading--inside"><div><h2>{tab === 'solar' ? 'Historial solar' : 'Historial del electrolizador'}</h2><p>Hasta 100 registros, ordenados desde el más reciente.</p></div></div>
                <div className="table-wrap"><table className="data-table"><thead><tr>{columns.map(([, label]) => <th key={label}>{label}</th>)}<th>Fecha</th></tr></thead><tbody>{data.map((item) => <tr key={item.id}>{columns.map(([key]) => <td key={key}>{String(item[key] ?? '—')}</td>)}<td>{new Date(item.created_at).toLocaleString('es-CO')}</td></tr>)}{!loading && data.length === 0 && <tr><td colSpan={columns.length + 1}><div className="table-empty"><BarChart3 size={24} /> Aún no se han recibido datos.</div></td></tr>}{loading && <tr><td colSpan={columns.length + 1}><div className="table-empty">Consultando resultados…</div></td></tr>}</tbody></table></div>
            </section>
        </div>
    );
}
