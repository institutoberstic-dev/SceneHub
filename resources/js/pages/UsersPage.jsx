import { CalendarDays, CheckCircle2, Info, Mail, Plus, Search, Shield, UserRound, Users } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import Modal from '../components/Modal';
import { api, csrfRequest, errorMessage } from '../http';

export default function UsersPage({ createOnLoad = false }) {
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [modalOpen, setModalOpen] = useState(createOnLoad);
    const [submitting, setSubmitting] = useState(false);
    const [query, setQuery] = useState('');
    const [feedback, setFeedback] = useState(null);
    const [form, setForm] = useState({ name: '', email: '', password: '' });

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await api.get('/api/users');
            setUsers(data);
        } catch (error) {
            setFeedback({ type: 'error', text: errorMessage(error, 'No fue posible consultar los usuarios.') });
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => { load(); }, [load]);

    const visibleUsers = useMemo(() => users.filter((user) => `${user.name} ${user.email}`.toLowerCase().includes(query.toLowerCase())), [query, users]);

    const submit = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        try {
            const { data } = await csrfRequest({ method: 'post', url: '/users-register', data: form });
            setFeedback({ type: 'success', text: data.message });
            setForm({ name: '', email: '', password: '' });
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
            <section className="page-heading"><div><span className="eyebrow">Administración</span><h1>Usuarios</h1><p>Consulta y registra las cuentas disponibles en la plataforma.</p></div><button className="primary-button" type="button" onClick={() => setModalOpen(true)}><Plus size={18} /> Nuevo usuario</button></section>
            {feedback && <div className={`alert alert--${feedback.type}`}>{feedback.type === 'success' ? <CheckCircle2 size={18} /> : <Info size={18} />}{feedback.text}</div>}
            <section className="metric-grid metric-grid--three"><article className="metric-card"><span className="metric-icon metric-icon--blue"><Users size={21} /></span><div><small>Total de usuarios</small><strong>{users.length}</strong><p>Cuentas registradas</p></div></article><article className="metric-card"><span className="metric-icon metric-icon--purple"><Shield size={21} /></span><div><small>Con rol asignado</small><strong>{users.filter((user) => user.roles?.length).length}</strong><p>Información referencial</p></div></article><article className="metric-card"><span className="metric-icon metric-icon--green"><CheckCircle2 size={21} /></span><div><small>Estado del módulo</small><strong>Disponible</strong><p>Sin middleware aplicado</p></div></article></section>
            <section className="content-card">
                <div className="toolbar"><label className="inline-search"><Search size={17} /><input placeholder="Buscar por nombre o correo" value={query} onChange={(event) => setQuery(event.target.value)} /></label></div>
                <div className="table-wrap"><table className="data-table"><thead><tr><th>Usuario</th><th>Correo</th><th>Roles</th><th>Fecha de registro</th></tr></thead><tbody>{visibleUsers.map((user) => <tr key={user.id}><td><span className="user-cell"><i>{user.name.slice(0, 2).toUpperCase()}</i><strong>{user.name}</strong></span></td><td><span className="muted-cell"><Mail size={15} />{user.email}</span></td><td><div className="role-list">{user.roles?.length ? user.roles.map((role) => <span className="status-pill status-pill--purple" key={role.id}>{role.name}</span>) : <span className="status-pill">Sin asignar</span>}</div></td><td><span className="muted-cell"><CalendarDays size={15} />{new Date(user.created_at).toLocaleDateString('es-CO')}</span></td></tr>)}{loading && <tr><td colSpan="4"><div className="table-empty">Cargando usuarios…</div></td></tr>}{!loading && visibleUsers.length === 0 && <tr><td colSpan="4"><div className="table-empty"><UserRound size={24} /> No hay usuarios para mostrar.</div></td></tr>}</tbody></table></div>
            </section>
            <Modal open={modalOpen} onClose={() => setModalOpen(false)} title="Registrar usuario" subtitle="No se asignarán permisos ni roles automáticamente."><form className="modal-form" onSubmit={submit}><label className="field-label" htmlFor="user-name">Nombre completo</label><input className="text-input" id="user-name" required value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} placeholder="Nombre del usuario" /><label className="field-label" htmlFor="user-email">Correo electrónico</label><input className="text-input" id="user-email" type="email" required value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} placeholder="usuario@institucion.edu" /><label className="field-label" htmlFor="user-password">Contraseña inicial</label><input className="text-input" id="user-password" type="password" minLength="8" required value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} placeholder="Mínimo 8 caracteres" /><div className="modal-actions"><button className="secondary-button" type="button" onClick={() => setModalOpen(false)}>Cancelar</button><button className="primary-button" type="submit" disabled={submitting}><Plus size={18} />{submitting ? 'Registrando…' : 'Registrar usuario'}</button></div></form></Modal>
        </div>
    );
}
