import { CheckCircle2, Folder, Grid2X2, Info, List, MoreHorizontal, Pencil, Plus, Search, Trash2, UploadCloud, UserRound, UserX } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import Modal from '../components/Modal';
import { api, csrfRequest, errorMessage } from '../http';

const tones = ['blue', 'green', 'purple', 'cyan'];

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
    const [form, setForm] = useState({ nombre: '', descripcion: '', archivo: null });
    const [uploadOpen, setUploadOpen] = useState(false);
    const [uploadForm, setUploadForm] = useState({ escenario_id: '', nombre: '', archivo: null });
    const [accessScenario, setAccessScenario] = useState(null);
    const [accessForm, setAccessForm] = useState({ email: '', access_level: 'editor' });
    const [editScenario, setEditScenario] = useState(null);
    const [editForm, setEditForm] = useState({ nombre: '', descripcion: '', estado: 'Activo', archivo: null });

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await api.get('/api/escenarios');
            setScenarios(data);
        } catch (error) {
            setFeedback({ type: 'error', text: errorMessage(error, 'No fue posible consultar los escenarios.') });
        } finally { setLoading(false); }
    }, []);

    useEffect(() => { load(); }, [load]);
    useEffect(() => {
        api.get('/api/me').then(({ data }) => {
            sessionStorage.setItem('scenehub_user', JSON.stringify(data));
            setUser(data);
        });
    }, []);

    const visibleScenarios = useMemo(() => scenarios.filter((scenario) => scenario.nombre.toLowerCase().includes(query.toLowerCase())), [query, scenarios]);
    const accessLevelFor = useCallback((scenario) => {
        if (isAdmin) return 'admin';
        if (scenario.owner_id === user?.id) return 'owner';
        return scenario.users?.find((member) => member.id === user?.id)?.pivot?.access_level || null;
    }, [isAdmin, user?.id]);
    const versionableScenarios = useMemo(() => scenarios.filter((scenario) => ['admin', 'owner'].includes(accessLevelFor(scenario))), [accessLevelFor, scenarios]);

    const submit = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        setFeedback(null);
        const body = new FormData();
        body.append('nombre', form.nombre);
        body.append('descripcion', form.descripcion);
        if (form.archivo) body.append('archivo', form.archivo);
        try {
            const { data } = await csrfRequest({ method: 'post', url: '/escenarios-store', data: body });
            setFeedback({ type: 'success', text: data.message });
            setForm({ nombre: '', descripcion: '', archivo: null });
            setModalOpen(false);
            await load();
        } catch (error) { setFeedback({ type: 'error', text: errorMessage(error) }); }
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

    const submitUpload = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        setFeedback(null);
        const body = new FormData();
        body.append('nombre', uploadForm.nombre);
        body.append('archivo', uploadForm.archivo);
        try {
            const { data } = await csrfRequest({ method: 'post', url: `/escenarios/${uploadForm.escenario_id}/contenidos`, data: body });
            setFeedback({ type: 'success', text: data.message });
            setUploadForm({ escenario_id: '', nombre: '', archivo: null });
            setUploadOpen(false);
            await load();
        } catch (error) { setFeedback({ type: 'error', text: errorMessage(error) }); }
        finally { setSubmitting(false); }
    };

    const openEdit = (scenario) => {
        setEditScenario(scenario);
        setEditForm({ nombre: scenario.nombre, descripcion: scenario.descripcion || '', estado: scenario.estado, archivo: null });
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
            if (editForm.archivo) body.append('archivo', editForm.archivo);
        }
        try {
            const { data } = await csrfRequest({ method: 'post', url: `/escenarios/${editScenario.id}`, data: body });
            setFeedback({ type: 'success', text: data.message });
            setEditScenario(null);
            await load();
        } catch (error) { setFeedback({ type: 'error', text: errorMessage(error) }); }
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
                <div><span className="eyebrow">Biblioteca de contenidos</span><h1>Carga de resultados del escenario</h1><p>Gestiona escenarios y consulta los archivos, responsables y versiones contenidos en cada uno.</p></div>
                <div className="heading-actions">{canCreate && <button className="primary-button" type="button" onClick={() => setModalOpen(true)}><Plus size={18} /> Agregar escenario</button>}{versionableScenarios.length > 0 && <button className="secondary-button" type="button" onClick={() => setUploadOpen(true)}><UploadCloud size={18} /> Subir nueva versión</button>}</div>
            </section>

            {feedback && <div className={`alert alert--${feedback.type}`}>{feedback.type === 'success' ? <CheckCircle2 size={18} /> : <Info size={18} />}{feedback.text}</div>}

            <section className="content-card">
                <div className="toolbar"><label className="inline-search"><Search size={17} /><input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Buscar escenario" /></label><div className="view-switch"><button className="active" type="button"><Grid2X2 size={17} /></button><button type="button"><List size={18} /></button></div></div>
                <div className="scenario-grid">
                    {loading && <div className="loading-card">Cargando escenarios…</div>}
                    {!loading && visibleScenarios.length === 0 && <div className="empty-scenario"><span><Folder size={22} /></span><strong>Sin escenarios accesibles</strong><p>Un cliente puede crear escenarios o participar como supervisor o editor mediante invitación.</p></div>}
                    {visibleScenarios.map((scenario, index) => { const accessLevel = accessLevelFor(scenario); return <article className="scenario-card scenario-card--clickable" key={scenario.id} onClick={() => navigate(`/escenarios/${scenario.id}`)}><div className="scenario-card__top"><span className={`folder-icon folder-icon--${tones[index % tones.length]}`}><Folder size={25} /></span><span className="scenario-card__actions">{['admin', 'owner', 'supervisor'].includes(accessLevel) && <button className="icon-button" type="button" onClick={(event) => { event.stopPropagation(); openEdit(scenario); }} aria-label={`Actualizar ${scenario.nombre}`} title={isAdmin ? 'Corregir nombre' : 'Actualizar escenario'}><Pencil size={17} /></button>}{accessLevel === 'owner' && <button className="icon-button" type="button" onClick={(event) => { event.stopPropagation(); setAccessScenario(scenario); }} aria-label={`Gestionar accesos de ${scenario.nombre}`} title="Gestionar accesos"><MoreHorizontal size={19} /></button>}</span></div><strong>{scenario.nombre}</strong><p className="scenario-card__description">{scenario.descripcion || 'Sin descripción'}</p><span className="status-pill status-pill--green"><i className="status-dot" /> {scenario.estado}</span><span className="status-pill status-pill--purple">Rol: {accessLevel}</span><footer><span>{scenario.contenidos_count} elementos · V{scenario.versiones}</span><span><UserRound size={14} /> {scenario.owner?.name || 'Sin owner'}</span></footer></article>; })}
                </div>
            </section>

            <Modal open={modalOpen} onClose={() => setModalOpen(false)} title="Nuevo escenario" subtitle="El usuario actual quedará registrado como owner del escenario.">
                <form className="modal-form" onSubmit={submit}><label className="field-label" htmlFor="scenario-name">Nombre del escenario</label><input className="text-input" id="scenario-name" required maxLength="255" value={form.nombre} onChange={(event) => setForm({ ...form, nombre: event.target.value })} placeholder="Ej. Escenario Base PEM" /><label className="field-label" htmlFor="scenario-description">Descripción del escenario</label><textarea className="text-input text-area" id="scenario-description" required maxLength="2000" value={form.descripcion} onChange={(event) => setForm({ ...form, descripcion: event.target.value })} placeholder="Describe el propósito, alcance o contenido del escenario" /><small className="field-help">{form.descripcion.length}/2000 caracteres</small><label className="upload-zone" htmlFor="scenario-file"><UploadCloud size={30} /><strong>{form.archivo?.name || 'Selecciona un archivo o paquete ZIP'}</strong><span>Tamaño máximo: 50 MB</span><input id="scenario-file" type="file" onChange={(event) => setForm({ ...form, archivo: event.target.files[0] || null })} /></label><div className="modal-actions"><button className="secondary-button" type="button" onClick={() => setModalOpen(false)}>Cancelar</button><button className="primary-button" type="submit" disabled={submitting}><Plus size={18} /> {submitting ? 'Guardando…' : 'Crear escenario'}</button></div></form>
            </Modal>

            <Modal open={Boolean(accessScenario)} onClose={() => setAccessScenario(null)} title="Gestionar acceso" subtitle={accessScenario ? `Invita un usuario a ${accessScenario.nombre}.` : ''}>
                <form className="modal-form" onSubmit={submitAccess}><label className="field-label" htmlFor="member-email">Correo del cliente registrado</label><input className="text-input" id="member-email" type="email" required value={accessForm.email} onChange={(event) => setAccessForm({ ...accessForm, email: event.target.value })} placeholder="usuario@institucion.edu" /><label className="field-label" htmlFor="member-level">Rol dentro del escenario</label><select className="text-input" id="member-level" value={accessForm.access_level} onChange={(event) => setAccessForm({ ...accessForm, access_level: event.target.value })}><option value="supervisor">Supervisor — consulta y actualiza datos</option><option value="editor">Editor — trabaja con contenido asignado</option></select><div className="member-list">{accessScenario?.users?.filter((member) => member.id !== accessScenario.owner_id).map((member) => <div className="member-list__item" key={member.id}><span><strong>{member.name}</strong><small>{member.email} · {member.pivot?.access_level}</small></span><button className="icon-button icon-button--danger" type="button" onClick={() => removeMember(accessScenario, member)} title="Retirar acceso"><UserX size={17} /></button></div>)}</div><div className="modal-actions"><button className="secondary-button" type="button" onClick={() => setAccessScenario(null)}>Cerrar</button><button className="primary-button" type="submit" disabled={submitting}><UserRound size={18} /> {submitting ? 'Asignando…' : 'Asignar rol'}</button></div></form>
            </Modal>

            <Modal open={uploadOpen} onClose={() => setUploadOpen(false)} title="Cargar resultado" subtitle="Añade un archivo a un escenario existente y crea automáticamente su siguiente versión.">
                <form className="modal-form" onSubmit={submitUpload}><label className="field-label" htmlFor="upload-scenario">Escenario</label><select className="text-input" id="upload-scenario" required value={uploadForm.escenario_id} onChange={(event) => setUploadForm({ ...uploadForm, escenario_id: event.target.value })}><option value="">Selecciona un escenario</option>{versionableScenarios.map((scenario) => <option value={scenario.id} key={scenario.id}>{scenario.nombre}</option>)}</select><label className="field-label" htmlFor="result-name">Nombre del resultado</label><input className="text-input" id="result-name" maxLength="255" value={uploadForm.nombre} onChange={(event) => setUploadForm({ ...uploadForm, nombre: event.target.value })} placeholder="Opcional; se usará el nombre del archivo si queda vacío" /><label className="upload-zone" htmlFor="result-file"><UploadCloud size={30} /><strong>{uploadForm.archivo?.name || 'Selecciona el archivo del resultado'}</strong><span>Obligatorio · Tamaño máximo: 50 MB</span><input id="result-file" type="file" required onChange={(event) => setUploadForm({ ...uploadForm, archivo: event.target.files[0] || null })} /></label><div className="modal-actions"><button className="secondary-button" type="button" onClick={() => setUploadOpen(false)}>Cancelar</button><button className="primary-button" type="submit" disabled={submitting}><UploadCloud size={18} /> {submitting ? 'Cargando…' : 'Cargar resultado'}</button></div></form>
            </Modal>

            <Modal open={Boolean(editScenario)} onClose={() => setEditScenario(null)} title="Actualizar escenario" subtitle={editScenario ? `Edita los datos de ${editScenario.nombre}. El archivo es opcional.` : ''}>
                <form className="modal-form" onSubmit={submitUpdate}><label className="field-label" htmlFor="edit-scenario-name">Nombre del escenario</label><input className="text-input" id="edit-scenario-name" required maxLength="255" value={editForm.nombre} onChange={(event) => setEditForm({ ...editForm, nombre: event.target.value })} />{!isAdmin && <><label className="field-label" htmlFor="edit-scenario-description">Descripción</label><textarea className="text-input text-area" id="edit-scenario-description" required maxLength="2000" value={editForm.descripcion} onChange={(event) => setEditForm({ ...editForm, descripcion: event.target.value })} /><small className="field-help">{editForm.descripcion.length}/2000 caracteres</small><label className="field-label" htmlFor="edit-scenario-status">Estado</label><select className="text-input" id="edit-scenario-status" value={editForm.estado} onChange={(event) => setEditForm({ ...editForm, estado: event.target.value })}><option value="Activo">Activo</option><option value="Inactivo">Inactivo</option></select>{editScenario && accessLevelFor(editScenario) === 'owner' && <label className="upload-zone" htmlFor="edit-scenario-file"><UploadCloud size={30} /><strong>{editForm.archivo?.name || 'Selecciona una actualización (opcional)'}</strong><span>{`Versión actual V${editScenario.versiones} · al adjuntar archivo se creará la siguiente versión`}</span><input id="edit-scenario-file" type="file" onChange={(event) => setEditForm({ ...editForm, archivo: event.target.files[0] || null })} /></label>}</>}<div className="modal-actions">{editScenario && ['admin', 'owner'].includes(accessLevelFor(editScenario)) && <button className="danger-button" type="button" onClick={() => deleteScenario(editScenario)}><Trash2 size={17} /> Eliminar</button>}<button className="secondary-button" type="button" onClick={() => setEditScenario(null)}>Cancelar</button><button className="primary-button" type="submit" disabled={submitting}><Pencil size={17} /> {submitting ? 'Actualizando…' : isAdmin ? 'Corregir nombre' : 'Guardar actualización'}</button></div></form>
            </Modal>
        </div>
    );
}
