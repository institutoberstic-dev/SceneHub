import { Ban, CalendarDays, CheckCircle2, FolderOpen, Info, Mail, Pencil, Plus, Search, Shield, Smile, UserRound, Users } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import Modal from '../components/Modal';
import Pagination from '../components/Pagination.jsx';
import { api, csrfRequest, errorMessage } from '../http';

const emptyForm = () => ({
    name: '',
    email: '',
    password: '',
    role: 'usuario',
    modules: ['escenarios'],
    is_active: true,
});

const moduleOptions = [
    { value: 'escenarios', title: 'Escenarios', description: 'Simulaciones, resultados y archivos', icon: FolderOpen },
    { value: 'emociones', title: 'Emociones', description: 'Webinars y resultados emocionales', icon: Smile },
];

export default function UsersPage({ createOnLoad = false }) {
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [modalOpen, setModalOpen] = useState(createOnLoad);
    const [editingUser, setEditingUser] = useState(null);
    const [submitting, setSubmitting] = useState(false);
    const [query, setQuery] = useState('');
    const [feedback, setFeedback] = useState(null);
    const [page, setPage] = useState(1);
    const [pageSize, setPageSize] = useState(15);
    const [form, setForm] = useState(emptyForm);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await api.get('/users-data');
            setUsers(data);
        } catch (error) {
            setFeedback({ type: 'error', text: errorMessage(error, 'No fue posible consultar los usuarios.') });
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => { load(); }, [load]);

    const visibleUsers = useMemo(() => users.filter((user) => `${user.name} ${user.email}`.toLowerCase().includes(query.toLowerCase())), [query, users]);
    const pagedUsers = visibleUsers.slice((page - 1) * pageSize, page * pageSize);
    const activeUsers = users.filter((user) => user.is_active).length;

    const openCreate = () => {
        setEditingUser(null);
        setForm(emptyForm());
        setModalOpen(true);
    };

    const openEdit = (user) => {
        setEditingUser(user);
        setForm({
            name: user.name,
            email: user.email,
            password: '',
            role: user.roles?.some((role) => role.name === 'admin') ? 'admin' : 'usuario',
            modules: user.modules || [],
            is_active: Boolean(user.is_active),
        });
        setModalOpen(true);
    };

    const closeModal = () => {
        setModalOpen(false);
        setEditingUser(null);
        setForm(emptyForm());
    };

    const toggleModule = (module) => {
        setForm((current) => ({
            ...current,
            modules: current.modules.includes(module)
                ? current.modules.filter((value) => value !== module)
                : [...current.modules, module],
        }));
    };

    const submit = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        try {
            const request = editingUser
                ? { method: 'put', url: `/users-update/${editingUser.id}`, data: form }
                : { method: 'post', url: '/users-register', data: form };
            const { data } = await csrfRequest(request);
            setFeedback({ type: 'success', text: data.message });
            closeModal();
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
                <div><span className="eyebrow">Administración</span><h1>Usuarios y accesos</h1><p>Crea cuentas, habilita módulos y controla su estado desde un solo lugar.</p></div>
                <button className="primary-button" type="button" onClick={openCreate}><Plus size={18} /> Nuevo usuario</button>
            </section>
            {feedback && <div className={`alert alert--${feedback.type}`} role="status">{feedback.type === 'success' ? <CheckCircle2 size={18} /> : <Info size={18} />}{feedback.text}</div>}
            <section className="metric-grid metric-grid--three">
                <article className="metric-card"><span className="metric-icon metric-icon--blue"><Users size={21} /></span><div><small>Total de usuarios</small><strong>{users.length}</strong><p>Cuentas registradas</p></div></article>
                <article className="metric-card"><span className="metric-icon metric-icon--green"><CheckCircle2 size={21} /></span><div><small>Activos</small><strong>{activeUsers}</strong><p>Con acceso habilitado</p></div></article>
                <article className="metric-card"><span className="metric-icon metric-icon--purple"><Ban size={21} /></span><div><small>Inhabilitados</small><strong>{users.length - activeUsers}</strong><p>Sin acceso a la plataforma</p></div></article>
            </section>
            <section className="content-card">
                <div className="toolbar"><label className="inline-search"><Search size={17} /><input placeholder="Buscar por nombre o correo" value={query} onChange={(event) => { setQuery(event.target.value); setPage(1); }} /></label></div>
                <div className="table-wrap">
                    <table className="data-table">
                        <thead><tr><th>Usuario</th><th>Rol</th><th>Módulos habilitados</th><th>Estado</th><th>Registro</th><th aria-label="Acciones" /></tr></thead>
                        <tbody>
                            {pagedUsers.map((user) => <tr key={user.id}>
                                <td><span className="user-cell"><i>{user.name.slice(0, 2).toUpperCase()}</i><span><strong>{user.name}</strong><small><Mail size={13} /> {user.email}</small></span></span></td>
                                <td><span className="status-pill status-pill--purple">{user.roles?.some((role) => role.name === 'admin') ? 'Administrador' : 'Usuario'}</span></td>
                                <td><div className="role-list">{user.modules?.length ? user.modules.map((module) => <span className="status-pill status-pill--blue" key={module}>{module}</span>) : <span className="status-pill">Sin módulos</span>}</div></td>
                                <td><span className={`status-pill ${user.is_active ? 'status-pill--green' : 'status-pill--danger'}`}><i className="status-dot" /> {user.is_active ? 'Activo' : 'Inhabilitado'}</span></td>
                                <td><span className="muted-cell"><CalendarDays size={15} />{new Date(user.created_at).toLocaleDateString('es-CO')}</span></td>
                                <td><button className="icon-button" type="button" onClick={() => openEdit(user)} aria-label={`Editar ${user.name}`} title="Editar usuario y accesos"><Pencil size={17} /></button></td>
                            </tr>)}
                            {loading && <tr><td colSpan="6"><div className="table-empty">Cargando usuarios…</div></td></tr>}
                            {!loading && visibleUsers.length === 0 && <tr><td colSpan="6"><div className="table-empty"><UserRound size={24} /> No hay usuarios para mostrar.</div></td></tr>}
                        </tbody>
                    </table>
                </div>
                {!loading && visibleUsers.length > 0 && <Pagination total={visibleUsers.length} page={page} pageSize={pageSize} onPageChange={setPage} onPageSizeChange={(size) => { setPageSize(size); setPage(1); }} />}
            </section>

            <Modal open={modalOpen} onClose={closeModal} title={editingUser ? 'Actualizar usuario' : 'Registrar usuario'} subtitle="El administrador define el estado de la cuenta y los módulos disponibles.">
                <form className="modal-form user-access-form" onSubmit={submit}>
                    <label className="field-label" htmlFor="user-name">Nombre completo</label>
                    <input className="text-input" id="user-name" required value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} placeholder="Nombre del usuario" />
                    <label className="field-label" htmlFor="user-email">Correo electrónico</label>
                    <input className="text-input" id="user-email" type="email" required value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} placeholder="usuario@institucion.edu" />
                    <label className="field-label" htmlFor="user-password">{editingUser ? 'Nueva contraseña (opcional)' : 'Contraseña inicial'}</label>
                    <input className="text-input" id="user-password" type="password" minLength="8" required={!editingUser} value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} placeholder={editingUser ? 'Déjala vacía para conservar la actual' : 'Mínimo 8 caracteres'} />
                    <label className="field-label" htmlFor="user-role">Rol global</label>
                    <select className="text-input" id="user-role" value={form.role} onChange={(event) => setForm({ ...form, role: event.target.value })}>
                        <option value="usuario">Usuario</option>
                        <option value="admin">Administrador</option>
                    </select>

                    <fieldset className="access-fieldset" disabled={form.role === 'admin'}>
                        <legend>Acceso por módulos</legend>
                        {form.role === 'admin' && <p>Los administradores tienen acceso completo a todos los módulos.</p>}
                        <div className="access-options">
                            {moduleOptions.map(({ value, title, description, icon: Icon }) => <label className={`access-option${form.modules.includes(value) || form.role === 'admin' ? ' access-option--selected' : ''}`} key={value}>
                                <input type="checkbox" checked={form.role === 'admin' || form.modules.includes(value)} onChange={() => toggleModule(value)} />
                                <span className="access-option__icon"><Icon size={20} /></span>
                                <span><strong>{title}</strong><small>{description}</small></span>
                            </label>)}
                        </div>
                    </fieldset>

                    <label className="account-state-option">
                        <input type="checkbox" checked={form.is_active} onChange={(event) => setForm({ ...form, is_active: event.target.checked })} />
                        <span><strong>Cuenta habilitada</strong><small>Si se desactiva, el usuario perderá el acceso inmediatamente.</small></span>
                    </label>

                    <div className="modal-actions"><button className="secondary-button" type="button" onClick={closeModal}>Cancelar</button><button className="primary-button" type="submit" disabled={submitting}><Shield size={18} />{submitting ? 'Guardando…' : editingUser ? 'Guardar cambios' : 'Registrar usuario'}</button></div>
                </form>
            </Modal>
        </div>
    );
}
