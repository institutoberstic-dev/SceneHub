import { CheckCircle2, Info, KeyRound, Mail, Shield, UserRound } from 'lucide-react';
import { useEffect, useState } from 'react';
import { api, csrfRequest, errorMessage } from '../http';

const emptyForm = { current_password: '', password: '', password_confirmation: '' };

function storedUser() {
    try {
        return JSON.parse(sessionStorage.getItem('scenehub_user') || 'null');
    } catch {
        return null;
    }
}

export default function ProfilePage() {
    const [user, setUser] = useState(storedUser);
    const [form, setForm] = useState(emptyForm);
    const [submitting, setSubmitting] = useState(false);
    const [feedback, setFeedback] = useState(null);

    useEffect(() => {
        api.get('/session-user').then(({ data }) => setUser(data)).catch(() => {
            // Se conserva la información guardada al iniciar sesión.
        });
    }, []);

    const isAdmin = user?.roles?.includes('admin');
    const modules = [
        (isAdmin || user?.permissions?.includes('escenarios.leer')) && 'Escenarios',
        (isAdmin || user?.permissions?.includes('emociones.leer')) && 'Emociones',
    ].filter(Boolean);

    const submit = async (event) => {
        event.preventDefault();
        setFeedback(null);

        if (form.password !== form.password_confirmation) {
            setFeedback({ type: 'error', text: 'La confirmación no coincide con la nueva contraseña.' });
            return;
        }

        setSubmitting(true);
        try {
            const { data } = await csrfRequest({ method: 'put', url: '/perfil/contrasena', data: form });
            setFeedback({ type: 'success', text: data.message });
            setForm(emptyForm);
        } catch (error) {
            setFeedback({
                type: 'error',
                text: error.response?.status === 429
                    ? 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.'
                    : errorMessage(error, 'No fue posible actualizar la contraseña.'),
            });
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <div className="page-stack">
            <section className="page-heading">
                <div><span className="eyebrow">Cuenta</span><h1>Mi perfil</h1><p>Consulta los datos de tu cuenta y cambia tu contraseña.</p></div>
            </section>

            <div className="profile-grid">
                <section className="content-card profile-card">
                    <h2><UserRound size={19} /> Datos de la cuenta</h2>
                    <dl className="profile-details">
                        <div><dt>Nombre</dt><dd>{user?.name || '—'}</dd></div>
                        <div><dt>Correo</dt><dd><Mail size={14} /> {user?.email || '—'}</dd></div>
                        <div><dt>Rol</dt><dd><span className="status-pill status-pill--purple">{isAdmin ? 'Administrador' : 'Usuario'}</span></dd></div>
                        <div>
                            <dt>Módulos</dt>
                            <dd className="role-list">
                                {modules.length
                                    ? modules.map((module) => <span className="status-pill status-pill--blue" key={module}>{module}</span>)
                                    : <span className="status-pill">Sin módulos</span>}
                            </dd>
                        </div>
                    </dl>
                    <p className="profile-note">Para cambiar tu nombre, correo o módulos, contacta al administrador.</p>
                </section>

                <section className="content-card profile-card">
                    <h2><KeyRound size={19} /> Cambiar contraseña</h2>
                    <form onSubmit={submit}>
                        <label className="field-label" htmlFor="current-password">Contraseña actual</label>
                        <input className="text-input" id="current-password" type="password" required autoComplete="current-password" value={form.current_password} onChange={(event) => setForm({ ...form, current_password: event.target.value })} />

                        <label className="field-label" htmlFor="new-password">Nueva contraseña</label>
                        <input className="text-input" id="new-password" type="password" required minLength="8" autoComplete="new-password" placeholder="Mínimo 8 caracteres" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} />

                        <label className="field-label" htmlFor="confirm-password">Confirmar nueva contraseña</label>
                        <input className="text-input" id="confirm-password" type="password" required minLength="8" autoComplete="new-password" value={form.password_confirmation} onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })} />

                        {feedback && <div className={`alert alert--${feedback.type} profile-alert`} role="status">{feedback.type === 'success' ? <CheckCircle2 size={18} /> : <Info size={18} />}{feedback.text}</div>}

                        <div className="profile-actions">
                            <button className="primary-button" type="submit" disabled={submitting}><Shield size={18} />{submitting ? 'Guardando…' : 'Actualizar contraseña'}</button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    );
}
