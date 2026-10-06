import { ArrowLeft, Mail, Send } from 'lucide-react';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import AuthLayout from '../components/AuthLayout';
import { csrfRequest, errorMessage } from '../http';

export default function ForgotPasswordPage() {
    const [email, setEmail] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [feedback, setFeedback] = useState(null);

    const submit = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        setFeedback(null);

        try {
            const { data } = await csrfRequest({ method: 'post', url: '/recuperar-contrasena', data: { email } });
            setFeedback({ type: 'success', text: data.message });
        } catch (error) {
            const text = error.response?.status === 429
                ? 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.'
                : errorMessage(error, 'No fue posible enviar el enlace. Inténtalo nuevamente.');
            setFeedback({ type: 'error', text });
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <AuthLayout title="Recuperar contraseña" subtitle="Te enviaremos un enlace para crear una contraseña nueva.">
            <form onSubmit={submit}>
                <label className="field-label" htmlFor="email">Correo institucional</label>
                <div className="input-with-icon">
                    <Mail size={19} />
                    <input id="email" type="email" required autoComplete="email" placeholder="usuario@institucion.edu" value={email} onChange={(event) => setEmail(event.target.value)} />
                </div>

                {feedback && <p className={`form-message form-message--${feedback.type} auth-message`} role="status">{feedback.text}</p>}

                <button className="primary-button primary-button--wide auth-submit" type="submit" disabled={submitting}>
                    <Send size={18} /> {submitting ? 'Enviando…' : 'Enviar enlace'}
                </button>
            </form>

            <Link className="auth-back-link" to="/login"><ArrowLeft size={16} /> Volver a iniciar sesión</Link>
        </AuthLayout>
    );
}
