import { CheckCircle2, Info, KeyRound, Plus, Search, Shield, UserRoundCog, Users } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import Modal from '../components/Modal';
import { api, csrfRequest, errorMessage } from '../http';

export default function RolesPage({ createOnLoad = false }) {
    const [roles, setRoles] = useState([]);
    const [loading, setLoading] = useState(true);
    const [modalOpen, setModalOpen] = useState(createOnLoad);
    const [submitting, setSubmitting] = useState(false);
    const [query, setQuery] = useState('');
    const [feedback, setFeedback] = useState(null);
    const [form, setForm] = useState({ name: '', guard_name: 'web' });

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await api.get('/api/roles');
            setRoles(data);
        } catch (error) {
            setFeedback({ type: 'error', text: errorMessage(error, 'No fue posible consultar los roles.') });
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => { load(); }, [load]);

    const visibleRoles = useMemo(() => roles.filter((role) => role.name.toLowerCase().includes(query.toLowerCase())), [query, roles]);

    const submit = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        try {
            const { data } = await csrfRequest({ method: 'post', url: '/roles-register', data: form });
            setFeedback({ type: 'success', text: data.message });
            setForm({ name: '', guard_name: 'web' });
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
            <section className="page-heading"><div><span className="eyebrow">Administración</span><h1>Roles globales</h1><p>Admin y cliente definen el alcance en la plataforma; owner, supervisor y editor se asignan dentro de cada escenario.</p></div><button className="primary-button" type="button" onClick={() => setModalOpen(true)}><Plus size={18} /> Nuevo rol global</button></section>
            {feedback && <div className={`alert alert--${feedback.type}`}>{feedback.type === 'success' ? <CheckCircle2 size={18} /> : <Info size={18} />}{feedback.text}</div>}
            <div className="info-banner"><Info size={19} /><div><strong>Autorización en dos niveles</strong><p>Spatie gestiona únicamente admin y cliente. Cada escenario asigna por separado los roles owner, supervisor y editor.</p></div></div>
            <section className="content-card"><div className="toolbar"><label className="inline-search"><Search size={17} /><input placeholder="Buscar rol" value={query} onChange={(event) => setQuery(event.target.value)} /></label></div><div className="role-grid">{visibleRoles.map((role, index) => <article className="role-card" key={role.id}><header><span className={`metric-icon metric-icon--${['blue', 'purple', 'green', 'cyan'][index % 4]}`}><Shield size={21} /></span><span className="status-pill status-pill--blue">{role.guard_name}</span></header><h2>{role.name}</h2><p>Estructura administrativa registrada en Spatie Permission.</p><footer><span><Users size={16} /> {role.users_count} usuarios</span><span><KeyRound size={16} /> {role.permissions_count} permisos</span></footer></article>)}{loading && <div className="loading-card">Cargando roles…</div>}{!loading && visibleRoles.length === 0 && <div className="table-empty"><UserRoundCog size={24} /> No hay roles para mostrar.</div>}</div></section>
            <Modal open={modalOpen} onClose={() => setModalOpen(false)} title="Crear rol" subtitle="Solo se registrará la identidad del rol; no se asignarán permisos."><form className="modal-form" onSubmit={submit}><label className="field-label" htmlFor="role-name">Nombre del rol</label><input className="text-input" id="role-name" required value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} placeholder="Ej. operador" /><label className="field-label" htmlFor="guard-name">Guard</label><select className="text-input" id="guard-name" value={form.guard_name} onChange={(event) => setForm({ ...form, guard_name: event.target.value })}><option value="web">web</option><option value="api">api</option></select><div className="modal-actions"><button className="secondary-button" type="button" onClick={() => setModalOpen(false)}>Cancelar</button><button className="primary-button" type="submit" disabled={submitting}><Plus size={18} />{submitting ? 'Creando…' : 'Crear rol'}</button></div></form></Modal>
        </div>
    );
}
