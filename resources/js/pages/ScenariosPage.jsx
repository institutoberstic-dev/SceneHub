import {
    Archive,
    CheckCircle2,
    Clock3,
    FileArchive,
    Folder,
    FolderOpen,
    Grid2X2,
    Info,
    List,
    MoreHorizontal,
    Plus,
    Search,
    UploadCloud,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import Modal from '../components/Modal';
import { api, csrfRequest, errorMessage } from '../http';

export default function ScenariosPage() {
    const [scenarios, setScenarios] = useState([]);
    const [loading, setLoading] = useState(true);
    const [modalOpen, setModalOpen] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [query, setQuery] = useState('');
    const [feedback, setFeedback] = useState(null);
    const [form, setForm] = useState({ nombre: '', archivo: null });

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await api.get('/api/escenarios');
            setScenarios(data);
        } catch (error) {
            setFeedback({ type: 'error', text: errorMessage(error, 'No fue posible consultar los escenarios.') });
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => { load(); }, [load]);

    useEffect(() => {
        const openCreate = () => setModalOpen(true);
        window.addEventListener('scenehub:create-scenario', openCreate);
        return () => window.removeEventListener('scenehub:create-scenario', openCreate);
    }, []);

    const visibleScenarios = useMemo(
        () => scenarios.filter((scenario) => scenario.nombre.toLowerCase().includes(query.toLowerCase())),
        [query, scenarios],
    );

    const submit = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        setFeedback(null);
        const body = new FormData();
        body.append('nombre', form.nombre);
        if (form.archivo) body.append('archivo', form.archivo);

        try {
            const { data } = await csrfRequest({ method: 'post', url: '/escenarios-store', data: body });
            setFeedback({ type: 'success', text: data.message });
            setForm({ nombre: '', archivo: null });
            setModalOpen(false);
            await load();
        } catch (error) {
            setFeedback({ type: 'error', text: errorMessage(error) });
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <div className="page-stack">
            <section className="page-heading">
                <div>
                    <span className="eyebrow">Biblioteca de contenidos</span>
                    <h1>Carga de escenarios</h1>
                    <p>Administra escenarios y adjunta el paquete asociado a su primera versión.</p>
                </div>
                <div className="heading-actions">
                    <button className="primary-button" type="button" onClick={() => setModalOpen(true)}><Plus size={18} /> Agregar escenario</button>
                    <button className="secondary-button" type="button" onClick={() => setModalOpen(true)}><UploadCloud size={18} /> Subir archivo</button>
                </div>
            </section>

            {feedback && <div className={`alert alert--${feedback.type}`}>{feedback.type === 'success' ? <CheckCircle2 size={18} /> : <Info size={18} />}{feedback.text}</div>}

            <section className="scenario-summary">
                <article><span className="metric-icon metric-icon--blue"><FolderOpen size={21} /></span><div><small>Total de escenarios</small><strong>{scenarios.length}</strong></div></article>
                <article><span className="metric-icon metric-icon--green"><CheckCircle2 size={21} /></span><div><small>Activos</small><strong>{scenarios.filter((item) => item.estado === 'Activo').length}</strong></div></article>
                <article><span className="metric-icon metric-icon--purple"><Archive size={21} /></span><div><small>Versiones registradas</small><strong>{scenarios.reduce((total, item) => total + Number(item.versiones || 0), 0)}</strong></div></article>
                <div className="context-note"><Info size={18} /><span>Los procesos de versionado y publicación son informativos por ahora.</span></div>
            </section>

            <section className="content-card">
                <div className="toolbar">
                    <label className="inline-search"><Search size={17} /><input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Buscar escenario" /></label>
                    <div className="view-switch"><button className="active" type="button"><Grid2X2 size={17} /></button><button type="button"><List size={18} /></button></div>
                </div>

                <div className="scenario-grid">
                    {loading && <div className="loading-card">Cargando escenarios…</div>}
                    {!loading && visibleScenarios.length === 0 && (
                        <button className="empty-scenario" type="button" onClick={() => setModalOpen(true)}>
                            <span><Plus size={22} /></span><strong>Crea tu primer escenario</strong><p>Registra un nombre y, si lo deseas, un archivo para la versión inicial.</p>
                        </button>
                    )}
                    {visibleScenarios.map((scenario, index) => (
                        <article className="scenario-card" key={scenario.id}>
                            <div className="scenario-card__top"><span className={`folder-icon folder-icon--${['blue', 'green', 'purple', 'cyan'][index % 4]}`}><Folder size={25} /></span><button className="icon-button"><MoreHorizontal size={19} /></button></div>
                            <strong>{scenario.nombre}</strong>
                            <span className="status-pill status-pill--green"><i className="status-dot" /> {scenario.estado}</span>
                            <footer><span>{scenario.versiones} {Number(scenario.versiones) === 1 ? 'versión' : 'versiones'}</span><span><Clock3 size={14} /> {new Date(scenario.updated_at).toLocaleDateString('es-CO')}</span></footer>
                        </article>
                    ))}
                </div>
            </section>

            <section className="content-card">
                <div className="section-heading section-heading--inside"><div><h2>Registro de escenarios</h2><p>Vista tabular conectada con EscenariosController.</p></div></div>
                <div className="table-wrap">
                    <table className="data-table">
                        <thead><tr><th>Escenario</th><th>Tipo</th><th>Última actualización</th><th>Versión</th><th>Estado</th></tr></thead>
                        <tbody>
                            {visibleScenarios.map((scenario) => <tr key={scenario.id}><td><span className="name-cell"><Folder size={18} /><strong>{scenario.nombre}</strong></span></td><td>Escenario</td><td>{new Date(scenario.updated_at).toLocaleString('es-CO')}</td><td>V{scenario.versiones}</td><td><span className="status-pill status-pill--green"><CheckCircle2 size={14} />{scenario.estado}</span></td></tr>)}
                            {!loading && visibleScenarios.length === 0 && <tr><td colSpan="5"><div className="table-empty"><FileArchive size={24} /> Sin elementos para mostrar.</div></td></tr>}
                        </tbody>
                    </table>
                </div>
            </section>

            <Modal open={modalOpen} onClose={() => setModalOpen(false)} title="Nuevo escenario" subtitle="El registro se enviará al controlador de escenarios.">
                <form className="modal-form" onSubmit={submit}>
                    <label className="field-label" htmlFor="scenario-name">Nombre del escenario</label>
                    <input className="text-input" id="scenario-name" required maxLength="255" value={form.nombre} onChange={(event) => setForm({ ...form, nombre: event.target.value })} placeholder="Ej. Escenario Base PEM" />
                    <label className="upload-zone" htmlFor="scenario-file">
                        <UploadCloud size={30} />
                        <strong>{form.archivo?.name || 'Selecciona un archivo o paquete ZIP'}</strong>
                        <span>Tamaño máximo: 50 MB</span>
                        <input id="scenario-file" type="file" onChange={(event) => setForm({ ...form, archivo: event.target.files[0] || null })} />
                    </label>
                    <div className="modal-actions"><button className="secondary-button" type="button" onClick={() => setModalOpen(false)}>Cancelar</button><button className="primary-button" type="submit" disabled={submitting}><Plus size={18} /> {submitting ? 'Guardando…' : 'Crear escenario'}</button></div>
                </form>
            </Modal>
        </div>
    );
}
