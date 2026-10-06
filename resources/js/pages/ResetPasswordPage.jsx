import { ArrowLeft, KeyRound, LockKeyhole, Mail } from 'lucide-react';
import { useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import AuthLayout from '../components/AuthLayout';
import { csrfRequest, errorMessage } from '../http';

export default function ResetPasswordPage() {
    const { token } = useParams();
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const [form, setForm] = useState({ email: searchParams.get('email') || '', password: '', password_confirmation: '' });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');

    const submit = async (event) => {
        event.preventDefault();
        setError('');

        if (form.password !== form.password_confirmation) {
            setError('La confirmación no coincide con la nueva contraseña.');
            return;
        }

        setSubmitting(true);
        try {
            await csrfRequest({ method: 'post', url: '/restablecer-contrasena', data: { ...form, token } });
            navigate('/login?reset=1', { replace: true });
        } catch (requestError) {
            setError(requestError.response?.status === 429
                ? 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.'
                : errorMessage(requestError, 'No fue posible restablecer la contraseña.'));
            setSubmitting(false);
        }
    };

    return (
        <AuthLayout title="Nueva contraseña" subtitle="Elige una contraseña de al menos 8 caracteres.">
            <form onSubmit={submit}>
                <label className="field-label" htmlFor="email">Correo institucional</label>
                <div className="input-with-icon">
                    <Mail size={19} />
                    <input id="email" type="email" required autoComplete="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} />
                </div>

                <label className="field-label" htmlFor="password">Nueva contraseña</label>
                <div className="input-with-icon">
                    <LockKeyhole size={19} />
                    <input id="password" type="password" required minLength="8" autoComplete="new-password" placeholder="Mínimo 8 caracteres" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} />
                </div>

                <label className="field-label" htmlFor="password_confirmation">Confirmar contraseña</label>
                <div className="input-with-icon">
                    <LockKeyhole size={19} />
                    <input id="password_confirmation" type="password" required minLength="8" autoComplete="new-password" placeholder="Repite la nueva contraseña" value={form.password_confirmation} onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })} />
                </div>

                {error && <p className="form-message form-message--error auth-message" role="alert">{error}</p>}

                <button className="primary-button primary-button--wide auth-submit" type="submit" disabled={submitting}>
                    <KeyRound size={18} /> {submitting ? 'Guardando…' : 'Guardar contraseña'}
                </button>
            </form>

            <Link className="auth-back-link" to="/recuperar-contrasena"><ArrowLeft size={16} /> Solicitar un enlace nuevo</Link>
        </AuthLayout>
    );
}
