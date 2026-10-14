import { CheckCircle2, ChevronRight, Info, Layers, MapPinned, Plus } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import Modal from '../components/Modal';
import { estados, fecha } from '../components/territorio';
import { api, csrfRequest, errorList, errorMessage } from '../http';

function FeedbackAlert({ feedback }) {
    if (!feedback) return null;
    return <div className={`alert alert--${feedback.type}`} role={feedback.type === 'error' ? 'alert' : 'status'}>{feedback.type === 'success' ? <CheckCircle2 size={18} /> : <Info size={18} />}<div className="alert__body"><span>{feedback.text}</span>{feedback.items?.length > 1 && <ul className="alert__list">{feedback.items.map((item) => <li key={item}>{item}</li>)}</ul>}</div></div>;
}

export default function TerritoryPage() {
    const navigate = useNavigate();
    const [data, setData] = useState({ paquetes: [], siguiente_version: 'v01', puede_crear: false });
    const [loading, setLoading] = useState(true);
    const [feedback, setFeedback] = useState(null);
    const [modalOpen, setModalOpen] = useState(false);
    const [form, setForm] = useState({ version: '', notas: '' });
    const [submitting, setSubmitting] = useState(false);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const { data: respuesta } = await api.get('/territorio-data');
            setData(respuesta);
        } catch (error) {
            setFeedback({ type: 'error', text: errorMessage(error, 'No fue posible consultar los paquetes territoriales.') });
        } finally { setLoading(false); }
    }, []);

    useEffect(() => { load(); }, [load]);

    const openCreate = () => {
        setForm({ version: data.siguiente_version, notas: '' });
        setFeedback(null);
        setModalOpen(true);
    };

    const submit = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        try {
            const { data: respuesta } = await csrfRequest({ method: 'post', url: '/territorio-paquetes', data: form });
            setModalOpen(false);
            navigate(`/territorio/${respuesta.data.id}`);
        } catch (error) {
            const items = errorList(error);
            setFeedback({ type: 'error', text: items[0], items });
        } finally { setSubmitting(false); }
    };

    if (loading) return <div className="loading-card">Cargando paquetes territoriales…</div>;

    return (
        <div className="page-stack">
            <section className="page-heading">
                <div>
                    <span className="eyebrow">Sistema territorial · PT1 / GAST</span>
                    <h1>Paquetes territoriales</h1>
                    <p>Repositorio versionado del paquete del PT1: malla, capas, valores de sitio y escena 3D. SceneHub guarda, valida y publica versiones inmutables; no reproyecta, no recalcula ni edita geometrías.</p>
                </div>
                {data.puede_crear && <div className="heading-actions"><button className="primary-button" type="button" onClick={openCreate}><Plus size={18} /> Nuevo paquete</button></div>}
            </section>

            {!modalOpen && <FeedbackAlert feedback={feedback} />}

            <section className="content-card">
                <div className="table-wrap">
                    <table className="data-table">
                        <thead><tr><th>Versión</th><th>Estado</th><th>Archivos</th><th>Validación</th><th>Creado por</th><th>Publicado</th><th /></tr></thead>
                        <tbody>
                            {data.paquetes.map((p) => (
                                <tr key={p.id} className="row-clickable" onClick={() => navigate(`/territorio/${p.id}`)}>
                                    <td><span className="name-cell"><Layers size={18} /><span className="name-cell__text"><strong>{p.version}</strong>{p.notas && <small>{p.notas}</small>}</span></span></td>
                                    <td><span className={`status-pill ${estados[p.estado]?.tone || ''}`}>{estados[p.estado]?.label || p.estado}</span></td>
                                    <td>{p.archivos_count}</td>
                                    <td>
                                        {p.errores > 0 && <span className="status-pill status-pill--danger">{p.errores} error(es)</span>}
                                        {p.errores === 0 && p.advertencias_pendientes > 0 && <span className="status-pill status-pill--warning">{p.advertencias_pendientes} por confirmar</span>}
                                        {p.errores === 0 && p.advertencias_pendientes === 0 && <span className="status-pill status-pill--green">Sin errores</span>}
                                    </td>
                                    <td>{p.creado_por || '—'}</td>
                                    <td>{fecha(p.publicado_en)}</td>
                                    <td><ChevronRight size={18} /></td>
                                </tr>
                            ))}
                            {data.paquetes.length === 0 && <tr><td colSpan="7"><div className="table-empty"><MapPinned size={24} /> Todavía no hay paquetes territoriales.{data.puede_crear ? ' Cree el primero con «Nuevo paquete».' : ''}</div></td></tr>}
                        </tbody>
                    </table>
                </div>
            </section>

            <Modal open={modalOpen} onClose={() => setModalOpen(false)} title="Nuevo paquete territorial" subtitle="Se crea como borrador. Luego podrá subir los archivos, validarlos y, tras la revisión, publicarlo.">
                <form className="modal-form" onSubmit={submit}>
                    <FeedbackAlert feedback={feedback?.type === 'error' ? feedback : null} />
                    <label className="field-label" htmlFor="territorio-version">Versión</label>
                    <input className="text-input" id="territorio-version" required pattern="v[0-9]{2,3}" value={form.version} onChange={(event) => setForm({ ...form, version: event.target.value.trim() })} />
                    <small className="field-help">Formato v01, v02… Una versión publicada no se puede cambiar: para corregir se crea otra.</small>
                    <label className="field-label" htmlFor="territorio-notas">Notas (opcional)</label>
                    <textarea className="text-input text-area" id="territorio-notas" maxLength="2000" value={form.notas} onChange={(event) => setForm({ ...form, notas: event.target.value })} placeholder="Qué cambia en esta versión, fuente o fecha del paquete del PT1" />
                    <div className="modal-actions"><button className="secondary-button" type="button" onClick={() => setModalOpen(false)}>Cancelar</button><button className="primary-button" type="submit" disabled={submitting}><Plus size={18} /> {submitting ? 'Creando…' : 'Crear borrador'}</button></div>
                </form>
            </Modal>
        </div>
    );
}
