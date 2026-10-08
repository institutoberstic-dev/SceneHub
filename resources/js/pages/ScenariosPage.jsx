import { CheckCircle2, Folder, Grid2X2, Info, List, MoreHorizontal, Pencil, Plus, Search, Trash2, UploadCloud, UserRound, UserX } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import Modal from '../components/Modal';
import Pagination from '../components/Pagination.jsx';
import ScenarioFileDropzone from '../components/ScenarioFileDropzone.jsx';
import TechnologySelector, { TechnologyChips } from '../components/TechnologySelector.jsx';
import { api, csrfRequest, errorList, errorMessage } from '../http';

const tones = ['blue', 'green', 'purple', 'cyan'];
const acceptedScenarioFiles = '.xlsx,.xls,.doc,.docx,.pdf';
// Fecha del archivo (File.lastModified): decide si un informe existente se actualiza.
const appendFiles = (body, files) => files.forEach((file, index) => { body.append('archivos[]', file); body.append(`fechas[${index}]`, String(file.lastModified || '')); });
const emptyForm = () => ({ nombre: '', descripcion: '', numero: '', tecnologias: [] });
const emptyDrop = () => ({ files: [], ready: false, analyzing: false, scenarioFile: null, suggestion: null, conflicts: false });
const errorFeedback = (error) => {
    const items = errorList(error);
    return { type: 'error', text: items.length > 1 ? 'Revisa los siguientes puntos antes de volver a intentarlo:' : items[0], items: items.length > 1 ? items : [] };
};

function FeedbackAlert({ feedback }) {
    if (!feedback) return null;
    return <div className={`alert alert--${feedback.type}`} role={feedback.type === 'error' ? 'alert' : 'status'}>{feedback.type === 'success' ? <CheckCircle2 size={18} /> : <Info size={18} />}<div className="alert__body"><span>{feedback.text}</span>{feedback.items?.length > 0 && <ul className="alert__list">{feedback.items.map(item => <li key={item}>{item}</li>)}</ul>}</div></div>;
}

function currentUser() {
    try { return JSON.parse(sessionStorage.getItem('scenehub_user') || 'null'); } catch { return null; }
}

export default function ScenariosPage() {
    const navigate = useNavigate();
    const [user, setUser] = useState(currentUser);
    const isAdmin = user?.roles?.includes('admin');
    const canCreate = !isAdmin && user?.permissions?.includes('escenarios.crear');
    const [scenarios, setScenarios] = useState([]);
    const [loading, setLoading] = useState(true);
    const [modalOpen, setModalOpen] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [query, setQuery] = useState('');
    const [feedback, setFeedback] = useState(null);
    const [page, setPage] = useState(1);
    const [pageSize, setPageSize] = useState(12);
    const [form, setForm] = useState(emptyForm);
    const [catalog, setCatalog] = useState([]);
    const [catalogState, setCatalogState] = useState({ loading: true, error: '' });
    const [newFiles, setNewFiles] = useState(emptyDrop);
    const touched = useRef({ nombre: false, numero: false });
    const [dropSession, setDropSession] = useState(0);
    const [uploadOpen, setUploadOpen] = useState(false);
    const [uploadForm, setUploadForm] = useState({ escenario_id: '', nombre: '' });
    const [uploadFiles, setUploadFiles] = useState(emptyDrop);
    const [accessScenario, setAccessScenario] = useState(null);
    const [accessForm, setAccessForm] = useState({ email: '', access_level: 'editor' });
    const [editScenario, setEditScenario] = useState(null);
    const [editForm, setEditForm] = useState({ nombre: '', descripcion: '', estado: 'Activo', archivo: null, tecnologias: [] });

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await api.get('/escenarios-data');
            setScenarios(data);
        } catch (error) {
            setFeedback({ type: 'error', text: errorMessage(error, 'No fue posible consultar los escenarios.') });
        } finally { setLoading(false); }
    }, []);

    useEffect(() => { load(); }, [load]);
    useEffect(() => {
        api.get('/tecnologias-data')
            .then(({ data }) => { setCatalog(Array.isArray(data) ? data : []); setCatalogState({ loading: false, error: '' }); })
            .catch((error) => setCatalogState({ loading: false, error: errorMessage(error, 'No fue posible consultar el catálogo de tecnologías.') }));
    }, []);
    useEffect(() => {
        api.get('/session-user').then(({ data }) => {
            sessionStorage.setItem('scenehub_user', JSON.stringify(data));
            setUser(data);
        }).catch(() => {
            // La sesión ya está disponible en sessionStorage; esta consulta no genera alertas visibles.
        });
    }, []);

    const visibleScenarios = useMemo(() => {
        const term = query.trim().toLowerCase();
        return scenarios.filter((scenario) => scenario.nombre.toLowerCase().includes(term) || String(scenario.numero ?? '') === term.replace(/^n\.?º?\s*/, ''));
    }, [query, scenarios]);
    const pagedScenarios = visibleScenarios.slice((page - 1) * pageSize, page * pageSize);
    const accessLevelFor = useCallback((scenario) => {
        if (isAdmin) return 'admin';
        if (scenario.owner_id === user?.id) return 'owner';
        return scenario.users?.find((member) => member.id === user?.id)?.pivot?.access_level || null;
    }, [isAdmin, user?.id]);
    const versionableScenarios = useMemo(() => scenarios.filter((scenario) => ['admin', 'owner'].includes(accessLevelFor(scenario))), [accessLevelFor, scenarios]);

    const openCreate = () => {
        setFeedback(null);
        setForm(emptyForm());
        setNewFiles(emptyDrop());
        touched.current = { nombre: false, numero: false };
        setDropSession((session) => session + 1);
        setModalOpen(true);
    };

    // Al reconocer el archivo de escenario se proponen su número y su nombre (si el usuario no los escribió).
    const handleNewFiles = useCallback((state) => {
        setNewFiles(state);
        if (!state.scenarioFile || !state.suggestion?.numero) return;
        setForm((current) => {
            const numero = touched.current.numero ? current.numero : String(state.suggestion.numero);
            return { ...current, numero, nombre: touched.current.nombre || !numero ? current.nombre : `Escenario ${numero}` };
        });
    }, []);

    const changeNumber = (value) => {
        touched.current.numero = value !== '';
        setForm((current) => ({ ...current, numero: value, nombre: touched.current.nombre || !value ? current.nombre : `Escenario ${value}` }));
    };

    const numberTaken = form.numero !== '' && (newFiles.suggestion?.ocupados || []).includes(Number(form.numero));

    const submit = async (event) => {
        event.preventDefault();
        if (!newFiles.ready || numberTaken) return;
        setSubmitting(true);
        setFeedback(null);
        const body = new FormData();
        body.append('nombre', form.nombre);
        body.append('descripcion', form.descripcion);
        if (form.numero !== '') body.append('numero', form.numero);
        form.tecnologias.forEach(codigo => body.append('tecnologias[]', codigo));
        appendFiles(body, newFiles.files);
        try {
            const { data } = await csrfRequest({ method: 'post', url: '/escenarios-store', data: body });
            setFeedback({ type: 'success', text: data.message });
            setForm(emptyForm());
            setNewFiles(emptyDrop());
            setModalOpen(false);
            await load();
        } catch (error) { setFeedback(errorFeedback(error)); }
        finally { setSubmitting(false); }
    };

    const submitAccess = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        setFeedback(null);
        try {
            const { data } = await csrfRequest({ method: 'post', url: `/escenarios/${accessScenario.id}/members`, data: accessForm });
            setFeedback({ type: 'success', text: data.message });
            setAccessForm({ email: '', access_level: 'editor' });
            setAccessScenario(null);
            await load();
        } catch (error) { setFeedback({ type: 'error', text: errorMessage(error) }); }
        finally { setSubmitting(false); }
    };

    const openUpload = () => {
        setFeedback(null);
        setUploadForm({ escenario_id: '', nombre: '' });
        setUploadFiles(emptyDrop());
        setDropSession((session) => session + 1);
        setUploadOpen(true);
    };

    const submitUpload = async (event) => {
        event.preventDefault();
        if (!uploadFiles.ready) return;
        setSubmitting(true);
        setFeedback(null);
        const body = new FormData();
        body.append('nombre', uploadForm.nombre);
        appendFiles(body, uploadFiles.files);
        try {
            const { data } = await csrfRequest({ method: 'post', url: `/escenarios/${uploadForm.escenario_id}/contenidos`, data: body });
            setFeedback({ type: 'success', text: data.message });
            setUploadForm({ escenario_id: '', nombre: '' });
            setUploadFiles(emptyDrop());
            setUploadOpen(false);
            await load();
        } catch (error) { setFeedback(errorFeedback(error)); }
        finally { setSubmitting(false); }
    };

    const openEdit = (scenario) => {
        setEditScenario(scenario);
        setFeedback(null);
        setEditForm({ nombre: scenario.nombre, descripcion: scenario.descripcion || '', estado: scenario.estado, archivo: null, tecnologias: (scenario.tecnologias || []).map(item => item.codigo) });
    };

    const submitUpdate = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        setFeedback(null);
        const body = new FormData();
        body.append('_method', 'PUT');
        body.append('nombre', editForm.nombre);
        if (!isAdmin) {
            body.append('descripcion', editForm.descripcion);
            body.append('estado', editForm.estado);
            // Un campo vacío indica al servidor que se quitaron todas las tecnologías.
            if (editForm.tecnologias.length) editForm.tecnologias.forEach(codigo => body.append('tecnologias[]', codigo));
            else body.append('tecnologias', '');
            if (editForm.archivo) { body.append('archivo', editForm.archivo); body.append('fecha_archivo', String(editForm.archivo.lastModified || '')); }
        }
        try {
            const { data } = await csrfRequest({ method: 'post', url: `/escenarios/${editScenario.id}`, data: body });
            setFeedback({ type: 'success', text: data.message });
            setEditScenario(null);
            await load();
        } catch (error) { setFeedback(errorFeedback(error)); }
        finally { setSubmitting(false); }
    };

    const removeMember = async (scenario, member) => {
        if (!window.confirm(`¿Retirar el acceso de ${member.name}? La cuenta no será eliminada.`)) return;
        try {
            const { data } = await csrfRequest({ method: 'delete', url: `/escenarios/${scenario.id}/members/${member.id}` });
            setFeedback({ type: 'success', text: data.message });
            setAccessScenario(null);
            await load();
        } catch (error) { setFeedback({ type: 'error', text: errorMessage(error) }); }
    };

    const deleteScenario = async (scenario) => {
        if (!window.confirm(`¿Eliminar definitivamente “${scenario.nombre}” y todos sus archivos?`)) return;
        try {
            const { data } = await csrfRequest({ method: 'delete', url: `/escenarios/${scenario.id}` });
            setFeedback({ type: 'success', text: data.message });
            setEditScenario(null);
            await load();
        } catch (error) { setFeedback({ type: 'error', text: errorMessage(error) }); }
    };

    return (
        <div className="page-stack">
            <section className="page-heading">
                <div><span className="eyebrow">Biblioteca de contenidos</span><h1>Archivos de escenarios</h1><p>Guarda documentos Word, PDF y Excel. Los libros de resultados de simulación (hojas Minutos, Cada5min, Cada10min y Horas) se validan, se publican en Resultados y se guardan como «resultados escenario N»; los archivos cuyo nombre dice «informe» quedan como «informe escenario N».</p></div>
                <div className="heading-actions">{canCreate && <button className="primary-button" type="button" onClick={openCreate}><Plus size={18} /> Agregar escenario</button>}{versionableScenarios.length > 0 && <button className="secondary-button" type="button" onClick={openUpload}><UploadCloud size={18} /> Agregar archivos</button>}</div>
            </section>

            {!(modalOpen || uploadOpen || editScenario || accessScenario) && <FeedbackAlert feedback={feedback} />}

            <section className="content-card">
                <div className="toolbar"><label className="inline-search"><Search size={17} /><input value={query} onChange={(event) => { setQuery(event.target.value); setPage(1); }} placeholder="Buscar por nombre o número" /></label><div className="view-switch"><button className="active" type="button"><Grid2X2 size={17} /></button><button type="button"><List size={18} /></button></div></div>
                <div className="scenario-grid">
                    {loading && <div className="loading-card">Cargando escenarios…</div>}
                    {!loading && visibleScenarios.length === 0 && <div className="empty-scenario"><span><Folder size={22} /></span><strong>Sin escenarios accesibles</strong><p>Un cliente puede crear escenarios o participar como supervisor o editor mediante invitación.</p></div>}
                    {pagedScenarios.map((scenario, index) => { const accessLevel = accessLevelFor(scenario); return <article className="scenario-card scenario-card--clickable" key={scenario.id} onClick={() => navigate(`/escenarios/${scenario.id}`)}><div className="scenario-card__top"><span className={`folder-icon folder-icon--${tones[index % tones.length]}`}><Folder size={25} /></span><span className="scenario-card__actions">{['admin', 'owner', 'supervisor'].includes(accessLevel) && <button className="icon-button" type="button" onClick={(event) => { event.stopPropagation(); openEdit(scenario); }} aria-label={`Actualizar ${scenario.nombre}`} title={isAdmin ? 'Corregir nombre' : 'Actualizar escenario'}><Pencil size={17} /></button>}{accessLevel === 'owner' && <button className="icon-button" type="button" onClick={(event) => { event.stopPropagation(); setFeedback(null); setAccessScenario(scenario); }} aria-label={`Gestionar accesos de ${scenario.nombre}`} title="Gestionar accesos"><MoreHorizontal size={19} /></button>}</span></div>{scenario.numero && <span className="scenario-card__number">Escenario N.º {scenario.numero}</span>}<strong>{scenario.nombre}</strong><p className="scenario-card__description">{scenario.descripcion || 'Sin descripción'}</p><TechnologyChips items={scenario.tecnologias || []} limit={3} /><span className="status-pill status-pill--green"><i className="status-dot" /> {scenario.estado}</span><span className="status-pill status-pill--purple">Rol: {accessLevel}</span><footer><span>{scenario.archivos_vigentes ?? scenario.contenidos_count} elementos · V{scenario.versiones}</span><span><UserRound size={14} /> {scenario.owner?.name || 'Sin owner'}</span></footer></article>; })}
                </div>
                {!loading && visibleScenarios.length > 0 && <Pagination total={visibleScenarios.length} page={page} pageSize={pageSize} onPageChange={setPage} onPageSizeChange={(size) => { setPageSize(size); setPage(1); }} />}
            </section>

            <Modal open={modalOpen} wide onClose={() => setModalOpen(false)} title="Nuevo escenario" subtitle="Arrastra los archivos: el sistema reconoce el archivo de datos del escenario y propone su número y su nombre. El usuario actual quedará como owner.">
                <form className="modal-form" onSubmit={submit}>
                    <FeedbackAlert feedback={feedback?.type === 'error' ? feedback : null} />
                    <h3 className="form-step"><span>1</span> Archivos del escenario</h3>
                    <ScenarioFileDropzone key={`new-${dropSession}`} id="scenario-files" scenarioNumber={form.numero} onChange={handleNewFiles} />
                    <h3 className="form-step"><span>2</span> Datos del escenario</h3>
                    <div className="field-grid">
                        <div>
                            <label className="field-label" htmlFor="scenario-number">Número</label>
                            <input className="text-input" id="scenario-number" type="number" min="1" max="9999" step="1" inputMode="numeric" value={form.numero} onChange={(event) => changeNumber(event.target.value)} placeholder="Automático" aria-invalid={numberTaken} aria-describedby="scenario-number-help" />
                            <small id="scenario-number-help" className={`field-help field-help--start ${numberTaken ? 'field-help--error' : ''}`}>{numberTaken ? `Ya existe un escenario con el número ${form.numero}.` : `Nombre del archivo de datos: «resultados escenario ${form.numero || 'N'}».`}</small>
                        </div>
                        <div>
                            <label className="field-label" htmlFor="scenario-name">Nombre del escenario</label>
                            <input className="text-input" id="scenario-name" required maxLength="255" value={form.nombre} onChange={(event) => { touched.current.nombre = event.target.value !== ''; setForm({ ...form, nombre: event.target.value }); }} placeholder="Ej. Escenario 1" />
                        </div>
                    </div>
                    <label className="field-label" htmlFor="scenario-description">Descripción del escenario</label><textarea className="text-input text-area" id="scenario-description" required maxLength="2000" value={form.descripcion} onChange={(event) => setForm({ ...form, descripcion: event.target.value })} placeholder="Describe el contenido del escenario" /><small className="field-help">{form.descripcion.length}/2000 caracteres</small>
                    <TechnologySelector idPrefix="new-tech" catalog={catalog} loading={catalogState.loading} error={catalogState.error} value={form.tecnologias} onChange={(tecnologias) => setForm({ ...form, tecnologias })} />
                    <div className="modal-actions"><button className="secondary-button" type="button" onClick={() => setModalOpen(false)}>Cancelar</button><button className="primary-button" type="submit" disabled={submitting || !newFiles.ready || numberTaken} title={!newFiles.ready ? 'Marca al menos un archivo válido para continuar' : undefined}><Plus size={18} /> {submitting ? 'Guardando…' : newFiles.analyzing ? 'Analizando archivos…' : 'Crear escenario'}</button></div>
                </form>
            </Modal>

            <Modal open={Boolean(accessScenario)} onClose={() => setAccessScenario(null)} title="Gestionar acceso" subtitle={accessScenario ? `Invita un usuario a ${accessScenario.nombre}.` : ''}>
                <form className="modal-form" onSubmit={submitAccess}><FeedbackAlert feedback={feedback?.type === 'error' ? feedback : null} /><label className="field-label" htmlFor="member-email">Correo del cliente registrado</label><input className="text-input" id="member-email" type="email" required value={accessForm.email} onChange={(event) => setAccessForm({ ...accessForm, email: event.target.value })} placeholder="usuario@institucion.edu" /><label className="field-label" htmlFor="member-level">Rol dentro del escenario</label><select className="text-input" id="member-level" value={accessForm.access_level} onChange={(event) => setAccessForm({ ...accessForm, access_level: event.target.value })}><option value="supervisor">Supervisor — consulta y actualiza datos</option><option value="editor">Editor — trabaja con contenido asignado</option></select><div className="member-list">{accessScenario?.users?.filter((member) => member.id !== accessScenario.owner_id).map((member) => <div className="member-list__item" key={member.id}><span><strong>{member.name}</strong><small>{member.email} · {member.pivot?.access_level}</small></span><button className="icon-button icon-button--danger" type="button" onClick={() => removeMember(accessScenario, member)} title="Retirar acceso"><UserX size={17} /></button></div>)}</div><div className="modal-actions"><button className="secondary-button" type="button" onClick={() => setAccessScenario(null)}>Cerrar</button><button className="primary-button" type="submit" disabled={submitting}><UserRound size={18} /> {submitting ? 'Asignando…' : 'Asignar rol'}</button></div></form>
            </Modal>

            <Modal open={uploadOpen} wide onClose={() => setUploadOpen(false)} title="Agregar archivos" subtitle="Un libro de resultados reemplaza el archivo de datos del escenario (nueva versión) y publica sus datos en Resultados. Los archivos cuyo nombre dice «informe» (Word o PDF) se guardan como informes numerados.">
                <form className="modal-form" onSubmit={submitUpload}>
                    <FeedbackAlert feedback={feedback?.type === 'error' ? feedback : null} />
                    <label className="field-label" htmlFor="upload-scenario">Escenario</label>
                    <select className="text-input" id="upload-scenario" required value={uploadForm.escenario_id} onChange={(event) => setUploadForm({ ...uploadForm, escenario_id: event.target.value })}><option value="">Selecciona un escenario</option>{versionableScenarios.map((scenario) => <option value={scenario.id} key={scenario.id}>{scenario.numero ? `N.º ${scenario.numero} · ` : ''}{scenario.nombre}</option>)}</select>
                    <ScenarioFileDropzone key={`upload-${dropSession}`} id="result-files" scenarioId={uploadForm.escenario_id || null} disabled={!uploadForm.escenario_id} onChange={setUploadFiles} />
                    <div className="modal-actions"><button className="secondary-button" type="button" onClick={() => setUploadOpen(false)}>Cancelar</button><button className="primary-button" type="submit" disabled={submitting || !uploadFiles.ready}><UploadCloud size={18} /> {submitting ? 'Cargando…' : uploadFiles.analyzing ? 'Analizando archivos…' : 'Agregar archivos'}</button></div>
                </form>
            </Modal>

            <Modal open={Boolean(editScenario)} onClose={() => setEditScenario(null)} title="Actualizar escenario" subtitle={editScenario ? `Edita los datos de ${editScenario.nombre}. El archivo es opcional.` : ''}>
                <form className="modal-form" onSubmit={submitUpdate}><FeedbackAlert feedback={feedback?.type === 'error' ? feedback : null} /><label className="field-label" htmlFor="edit-scenario-name">Nombre del escenario</label><input className="text-input" id="edit-scenario-name" required maxLength="255" value={editForm.nombre} onChange={(event) => setEditForm({ ...editForm, nombre: event.target.value })} />{!isAdmin && <><label className="field-label" htmlFor="edit-scenario-description">Descripción</label><textarea className="text-input text-area" id="edit-scenario-description" required maxLength="2000" value={editForm.descripcion} onChange={(event) => setEditForm({ ...editForm, descripcion: event.target.value })} /><small className="field-help">{editForm.descripcion.length}/2000 caracteres</small><label className="field-label" htmlFor="edit-scenario-status">Estado</label><select className="text-input" id="edit-scenario-status" value={editForm.estado} onChange={(event) => setEditForm({ ...editForm, estado: event.target.value })}><option value="Activo">Activo</option><option value="Inactivo">Inactivo</option></select><TechnologySelector idPrefix="edit-tech" catalog={catalog} current={editScenario?.tecnologias || []} loading={catalogState.loading} error={catalogState.error} value={editForm.tecnologias} onChange={(tecnologias) => setEditForm({ ...editForm, tecnologias })} />{editScenario && accessLevelFor(editScenario) === 'owner' && <label className="upload-zone" htmlFor="edit-scenario-file"><UploadCloud size={30} /><strong>{editForm.archivo?.name || 'Selecciona una actualización (opcional)'}</strong><span>{`Word, PDF o Excel · versión actual V${editScenario.versiones}. Los resultados se guardan como «resultados escenario ${editScenario.numero ?? 'N'}» y los informes como «informe escenario N».`}</span><input id="edit-scenario-file" type="file" accept={acceptedScenarioFiles} onChange={(event) => setEditForm({ ...editForm, archivo: event.target.files[0] || null })} /></label>}</>}<div className="modal-actions">{editScenario && ['admin', 'owner'].includes(accessLevelFor(editScenario)) && <button className="danger-button" type="button" onClick={() => deleteScenario(editScenario)}><Trash2 size={17} /> Eliminar</button>}<button className="secondary-button" type="button" onClick={() => setEditScenario(null)}>Cancelar</button><button className="primary-button" type="submit" disabled={submitting}><Pencil size={17} /> {submitting ? 'Actualizando…' : isAdmin ? 'Corregir nombre' : 'Guardar actualización'}</button></div></form>
            </Modal>
        </div>
    );
}
