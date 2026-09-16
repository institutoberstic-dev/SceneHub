import {
    ArrowRight,
    CloudUpload,
    DatabaseZap,
    Eye,
    EyeOff,
    Languages,
    LockKeyhole,
    LogIn,
    Mail,
    PlaySquare,
    RefreshCw,
    ShieldCheck,
    Target,
    TrendingUp,
} from 'lucide-react';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import Brand from '../components/Brand';
import { csrfRequest, errorMessage } from '../http';

const pipeline = [
    { number: 1, title: 'AnyLogic', text: 'Modelado y generación de datos de simulación', icon: TrendingUp, tone: 'blue' },
    { number: 2, title: 'ETL + Compactación', text: 'Limpieza, validación y reducción de datos', icon: DatabaseZap, tone: 'green' },
    { number: 3, title: 'Publicación', text: 'Carga controlada con versionado', icon: CloudUpload, tone: 'purple' },
    { number: 4, title: 'Sincronización', text: 'Actualización y verificación de integridad', icon: RefreshCw, tone: 'cyan' },
    { number: 5, title: 'Reproducción', text: 'Visualización interactiva de resultados', icon: PlaySquare, tone: 'orange' },
];

export default function LoginPage() {
    const navigate = useNavigate();
    const [showPassword, setShowPassword] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [message, setMessage] = useState(() => new URLSearchParams(window.location.search).has('disabled')
        ? 'Tu cuenta está inhabilitada. Contacta al administrador.'
        : '');
    const [form, setForm] = useState({ email: '', password: '', remember: true });

    const submit = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        setMessage('');

        try {
            const { data } = await csrfRequest({ method: 'post', url: '/log-in', data: form });
            sessionStorage.setItem('scenehub_user', JSON.stringify(data.user));
            navigate('/dashboard');
        } catch (error) {
            setMessage(errorMessage(error, 'No fue posible iniciar sesión.'));
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <div className="login-page">
            <header className="login-topbar">
                <Brand compact />
                <span className="login-platform">Plataforma de Integración y Comunicación</span>
                <div className="login-topbar__right">
                    <span className="secure-badge"><ShieldCheck size={17} /> Entorno seguro</span>
                    <b className="version-badge">v2.0</b>
                    <span className="language"><Languages size={18} /> Español</span>
                </div>
            </header>

            <main className="login-layout">
                <section className="login-story">
                    <div className="login-story__content">
                        <span className="eyebrow">SceneHub · Capa 2</span>
                        <h1>Plataforma de<br />Integración y Comunicación</h1>
                        <p className="lead">Gestiona escenarios, versiones y resultados entre simulación y visualización.</p>

                        <div className="pipeline">
                            {pipeline.map(({ number, title, text, icon: Icon, tone }, index) => (
                                <div className="pipeline-step-wrap" key={title}>
                                    <article className={`pipeline-card pipeline-card--${tone}`}>
                                        <Icon size={36} />
                                        <strong><i>{number}</i>{title}</strong>
                                        <p>{text}</p>
                                    </article>
                                    {index < pipeline.length - 1 && <ArrowRight className="pipeline-arrow" size={20} />}
                                </div>
                            ))}
                        </div>

                        <div className="login-metrics">
                            <span><Target /> <b>Precisión</b><small>Datos controlados</small></span>
                            <span><TrendingUp /> <b>Escalabilidad</b><small>Arquitectura flexible</small></span>
                            <span><ShieldCheck /> <b>Versionado</b><small>Historial confiable</small></span>
                            <span><RefreshCw /> <b>Disponibilidad</b><small>Integración continua</small></span>
                        </div>
                    </div>
                </section>

                <section className="login-panel">
                    <form className="login-card" onSubmit={submit}>
                        <Brand />
                        <div className="login-card__heading">
                            <h2>Iniciar sesión</h2>
                            <p>Accede para administrar escenarios y versiones.</p>
                        </div>

                        <label className="field-label" htmlFor="email">Correo institucional</label>
                        <div className="input-with-icon">
                            <Mail size={19} />
                            <input id="email" type="email" required placeholder="usuario@institucion.edu" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} />
                        </div>

                        <label className="field-label" htmlFor="password">Contraseña</label>
                        <div className="input-with-icon">
                            <LockKeyhole size={19} />
                            <input id="password" type={showPassword ? 'text' : 'password'} required placeholder="Ingresa tu contraseña" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} />
                            <button type="button" onClick={() => setShowPassword((value) => !value)} aria-label="Mostrar contraseña">
                                {showPassword ? <EyeOff size={19} /> : <Eye size={19} />}
                            </button>
                        </div>

                        <div className="login-options">
                            <label><input type="checkbox" checked={form.remember} onChange={(event) => setForm({ ...form, remember: event.target.checked })} /> Recordarme</label>
                            <span>¿Olvidaste tu contraseña?</span>
                        </div>

                        {message && <p className="form-message form-message--error">{message}</p>}

                        <button className="primary-button primary-button--wide" type="submit" disabled={submitting}>
                            <LogIn size={19} /> {submitting ? 'Ingresando…' : 'Iniciar sesión'}
                        </button>

                        <div className="login-security">
                            <LockKeyhole size={17} />
                            <p><strong>Acceso para cuentas registradas.</strong><br />Sesión protegida mediante CSRF y cifrado de Laravel.</p>
                        </div>
                    </form>
                </section>
            </main>
        </div>
    );
}
