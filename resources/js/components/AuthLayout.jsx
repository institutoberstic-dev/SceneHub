import { ShieldCheck } from 'lucide-react';
import Brand from './Brand';

/** Marco común de las pantallas sin sesión (recuperar y restablecer contraseña). */
export default function AuthLayout({ title, subtitle, children }) {
    return (
        <div className="login-page">
            <header className="login-topbar">
                <Brand compact />
                <span className="login-platform">Plataforma de Integración y Comunicación</span>
                <div className="login-topbar__right">
                    <span className="secure-badge"><ShieldCheck size={17} /> Entorno seguro</span>
                </div>
            </header>

            <main className="auth-standalone">
                <div className="login-card">
                    <Brand />
                    <div className="login-card__heading">
                        <h2>{title}</h2>
                        <p>{subtitle}</p>
                    </div>
                    {children}
                </div>
            </main>
        </div>
    );
}
