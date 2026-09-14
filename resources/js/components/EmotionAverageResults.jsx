import { useEffect, useState } from 'react';
import { api, errorMessage } from '../http';
import Pagination from './Pagination.jsx';

export default function EmotionAverageResults({ meeting }) {
    const [scenario, setScenario] = useState('');
    const [version, setVersion] = useState('');
    const [page, setPage] = useState(1);
    const [size, setSize] = useState(15);
    const [data, setData] = useState(null);
    const [failure, setFailure] = useState('');
    const [loading, setLoading] = useState(true);
    const [reload, setReload] = useState(0);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true); setFailure('');
        api.get(`/api/emociones/${meeting}`, { signal: controller.signal, params: { escenario_id: scenario || undefined, version: version || undefined } })
            .then(({ data: result }) => { setData(result); setPage(1); })
            .catch(error => { if (!controller.signal.aborted) setFailure(errorMessage(error)); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [meeting, scenario, version, reload]);
    const rows = data?.summary ?? [];
    return <section className="content-card solar-body" aria-busy={loading}>
        <div className="solar-actions"><h2>Promedio del archivo</h2><button type="button" className="secondary-button" onClick={() => setReload(value => value + 1)}>Actualizar</button></div>
        <div className="solar-filters">
            <label>Escenario<select className="text-input" disabled={loading} value={scenario || data?.scenario?.id || ''} onChange={e => { setScenario(e.target.value); setVersion(''); }}><option value="" disabled>Selecciona un escenario</option>{data?.scenarios.map(item => <option key={item.id} value={item.id}>{item.nombre}</option>)}</select></label>
            <label>Versión<select className="text-input" disabled={loading || !data?.versions?.length} value={version} onChange={e => setVersion(e.target.value)}><option value="">Última disponible{data?.version ? ` (${data.version})` : ''}</option>{data?.versions?.map(item => <option key={item.id} value={item.version}>{item.version}</option>)}</select></label>
        </div>
        {failure && <p role="alert">{failure}</p>}
        {loading && <p role="status">Cargando promedio…</p>}
        {!loading && !failure && !rows.length && <p className="solar-empty">No hay resultados del nuevo formato de promedio para este webinar y versión.</p>}
        {!loading && !failure && rows.length > 0 && <>
            <div className="solar-table"><table><thead><tr><th>ID de reunión</th><th>Nivel de atención promedio</th><th>Emoción ganadora promedio</th></tr></thead><tbody className="page-fade" key={`${page}-${size}-${data?.version}`}>{rows.slice((page - 1) * size, page * size).map(row => <tr key={row.id_meeting}><td>{row.id_meeting}</td><td>{row.nivel_atencion_prom}</td><td>{row.emocion_ganadora_prom}</td></tr>)}</tbody></table></div>
            <Pagination total={rows.length} page={page} pageSize={size} onPageChange={setPage} onPageSizeChange={value => { setSize(value); setPage(1); }} />
        </>}
    </section>;
}
