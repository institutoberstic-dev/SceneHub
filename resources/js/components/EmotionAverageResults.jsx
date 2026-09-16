import { useEffect, useState } from 'react';
import { api, errorMessage } from '../http';
import Pagination from './Pagination.jsx';

export default function EmotionAverageResults({ meeting }) {
    const [page, setPage] = useState(1);
    const [data, setData] = useState(null);
    const [failure, setFailure] = useState('');
    const [loading, setLoading] = useState(true);
    const [reload, setReload] = useState(0);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true); setFailure('');
        api.get(`/emociones-data/${meeting}?page=${page}`, { signal: controller.signal })
            .then(({ data: result }) => setData(result))
            .catch(error => { if (!controller.signal.aborted) setFailure(errorMessage(error)); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [meeting, reload, page]);
    useEffect(() => setPage(1), [meeting, reload]);
    const rows = data?.summary ?? [];
    const detailRows = data?.details?.data ?? [];
    const detailTotal = data?.details?.total ?? 0;
    return <section className="content-card solar-body" aria-busy={loading}>
        <div className="solar-actions"><h2>Datos del webinar</h2><button type="button" className="secondary-button" onClick={() => setReload(value => value + 1)}>Actualizar</button></div>
        {failure && <p role="alert">{failure}</p>}
        {loading && <p role="status">Cargando datos…</p>}
        {!loading && !failure && !rows.length && !detailTotal && <p className="solar-empty">No hay resultados cargados para este webinar.</p>}
        {!loading && !failure && <section aria-label="Promedio emocional del webinar">
            <h3 className="data-section-title">Resumen promedio</h3>
            <p>Atención y emoción promedio del archivo cargado para este webinar.</p>
            {rows.length ? <div className="solar-table"><table><thead><tr><th>ID de reunión</th><th>Nivel de atención promedio</th><th>Emoción ganadora promedio</th></tr></thead><tbody>{rows.map(row => <tr key={row.id_meeting}><td>{row.id_meeting}</td><td>{row.nivel_atencion_prom}</td><td>{row.emocion_ganadora_prom}</td></tr>)}</tbody></table></div> : <p className="solar-empty">Todavía no se ha cargado el archivo promedio de este webinar.</p>}
        </section>}
        {!loading && !failure && detailTotal > 0 && <>
            <h3 className="data-section-title">Detalle por persona ({detailTotal.toLocaleString('es-CO')} registros)</h3>
            <div className="solar-table"><table><thead><tr><th>Persona</th><th>Archivo</th><th>Nivel de atención</th><th>Emoción</th><th>Fecha</th><th>Hora</th><th>Validez</th></tr></thead><tbody className="page-fade">{detailRows.map(row => <tr key={row.id}><td>{row.id_persona}</td><td>{row.archivo}</td><td>{row.nivel_atencion}</td><td>{row.emocion_ganadora}</td><td>{row.fecha}</td><td>{row.tiempo}</td><td>{row.validez ? 'Válido' : 'No válido'}</td></tr>)}</tbody></table></div>
            <Pagination total={detailTotal} page={page} pageSize={50} onPageChange={setPage} />
        </>}
    </section>;
}
