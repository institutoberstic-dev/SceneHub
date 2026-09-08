import { ArrowLeft } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api, errorMessage } from '../http';

const emotions = ['angry', 'disgust', 'fear', 'happy', 'neutral', 'sad', 'surprise'];
const percent = (value) => value == null ? '—' : `${(Number(value) * 100).toFixed(2)} %`;

function Pager({ data, change }) {
    return <div className="modal-actions">
        <button className="secondary-button" disabled={data.current_page <= 1} onClick={() => change(data.current_page - 1)}>Anterior</button>
        <span>Página {data.current_page} de {data.last_page} · {data.total} registros</span>
        <button className="secondary-button" disabled={data.current_page >= data.last_page} onClick={() => change(data.current_page + 1)}>Siguiente</button>
    </div>;
}

export default function EmotionDetailPage() {
    const { id } = useParams();
    const meeting = id;
    const [scenario, setScenario] = useState('');
    const [page, setPage] = useState(1);
    const [averagePage, setAveragePage] = useState(1);
    const [data, setData] = useState(null);
    const [failure, setFailure] = useState('');
    const [loading, setLoading] = useState(true);

    useEffect(() => { setScenario(''); setPage(1); setAveragePage(1); }, [id]);

    useEffect(() => {
        let active = true;
        setLoading(true);
        setFailure('');
        api.get(`/api/emociones/${meeting}`, { params: { page, averages_page: averagePage, escenario_id: scenario || undefined } })
            .then(({ data: result }) => { if (active) setData(result); })
            .catch((error) => { if (active) setFailure(errorMessage(error, 'No fue posible consultar la sesión.')); })
            .finally(() => { if (active) setLoading(false); });
        return () => { active = false; };
    }, [scenario, meeting, page, averagePage]);

    return <div className="page-stack">
        <Link className="back-link" to="/emociones"><ArrowLeft size={17} /> Volver a Emociones</Link>
        <section className="page-heading"><div><span className="eyebrow">Contenido del webinar</span><h1>Webinar {meeting}</h1><p>Promedios y registros completos relacionados con este webinar.</p></div></section>
        {data?.scenarios.length > 0 && <label className="field-label">Escenario
            <select className="text-input" value={scenario || data.scenario?.id || ''} onChange={(event) => { setScenario(event.target.value); setPage(1); setAveragePage(1); }}>
                {data.scenarios.map((item) => <option key={item.id} value={item.id}>{item.nombre}</option>)}
            </select>
        </label>}
        {failure && <div className="alert alert--error">{failure}</div>}
        {loading && <div className="content-card loading-card">Cargando emociones…</div>}
        {!loading && !failure && data && <>
            <section className="content-card"><h2>Totales del webinar</h2><p>{data.totals.records} registros · {data.totals.people} personas · {data.totals.valid} válidos · {data.totals.invalid} no válidos</p></section>
            <section className="content-card">
                <h2>Promedios por persona</h2>
                {data.averages.total === 0 ? <p>No se ha cargado un archivo de promedios.</p> : <>
                    <div style={{ overflowX: 'auto' }}><table className="data-table"><thead><tr><th>Persona</th><th>Emoción</th><th>Atención</th>{emotions.map((emotion) => <th key={emotion}>{emotion}</th>)}</tr></thead>
                        <tbody>{data.averages.data.map((row) => <tr key={row.id}><td>{row.id_persona}</td><td>{row.emocion_prom}</td><td>{percent(row.score_atencion_prom)}</td>{emotions.map((emotion) => <td key={emotion}>{percent(row[`prob_${emotion}_prom`])}</td>)}</tr>)}</tbody>
                    </table></div><Pager data={data.averages} change={setAveragePage} />
                </>}
            </section>
            <section className="content-card">
                <h2>Detalle de emociones</h2>
                {data.details.total === 0 ? <p>No se ha cargado un archivo de detalle.</p> : <>
                    <div style={{ overflowX: 'auto' }}><table className="data-table"><thead><tr><th>Persona</th><th>Fecha</th><th>Hora</th><th>Archivo</th><th>Nivel de atención</th><th>Atención</th><th>Emoción</th>{emotions.map((emotion) => <th key={emotion}>{emotion}</th>)}<th>Validez</th><th>Calidad DAMA</th></tr></thead>
                        <tbody>{data.details.data.map((row) => <tr key={row.id}><td>{row.id_persona}</td><td>{row.fecha}</td><td>{row.tiempo}</td><td>{row.archivo}</td><td>{row.nivel_atencion}</td><td>{percent(row.score_atencion)}</td><td>{row.emocion_ganadora}</td>{emotions.map((emotion) => <td key={emotion}>{percent(row[`prob_${emotion}`])}</td>)}<td>{Number(row.validez) ? 'VÁLIDO' : 'NO VÁLIDO'}</td><td>{row.estatus_calidad_DAMA}</td></tr>)}</tbody>
                    </table></div><Pager data={data.details} change={setPage} />
                </>}
            </section>
        </>}
    </div>;
}
