import {
    BarChart3,
    Bell,
    Boxes,
    FolderOpen,
    Home,
    LogOut,
    Menu,
    Search,
    ShieldCheck,
    Users,
    UserRoundCog,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { NavLink, useLocation, useNavigate } from 'react-router-dom';
import { api, csrfRequest, errorMessage } from '../http';

const mainNavigation = [
    { label: 'Inicio', path: '/dashboard', icon: Home },
    { label: 'Escenarios', path: '/escenarios', icon: FolderOpen },
    { label: 'Resultados', path: '/resultados', icon: BarChart3 },
    { label: 'Emociones', path: '/emociones', faIcon: 'fa-face-smile-beam' },
];

const adminNavigation = [
    { label: 'Usuarios', path: '/users-list', icon: Users },
    { label: 'Roles', path: '/roles-list', icon: UserRoundCog },
];

const pageMeta = {
    '/dashboard': ['Inicio', 'Hub de escenarios'],
    '/escenarios': ['Escenarios', 'Carga y administración'],
    '/resultados': ['Resultados', 'Telemetría recibida'],
    '/emociones': ['Emociones', 'Catálogo emocional'],
    '/users-list': ['Administración', 'Usuarios'],
    '/users-create': ['Administración', 'Usuarios'],
    '/roles-list': ['Administración', 'Roles'],
    '/roles-create': ['Administración', 'Roles'],
};

function storedUser() {
    try {
        return JSON.parse(sessionStorage.getItem('scenehub_user') || 'null');
    } catch {
        sessionStorage.removeItem('scenehub_user');
        return null;
    }
}

export default function AppLayout({ children }) {
    const [menuOpen, setMenuOpen] = useState(false);
    const [loggingOut, setLoggingOut] = useState(false);
    const [logoutError, setLogoutError] = useState('');
    const location = useLocation();
    const navigate = useNavigate();
    const meta = location.pathname.startsWith('/emociones/')
        ? ['Emociones', 'Contenido de la sesión']
        : pageMeta[location.pathname] || ['SceneHub', 'Módulo'];
    const [user, setUser] = useState(storedUser);
    const isAdmin = user?.roles?.includes('admin');
    const initials = user?.name
        ? user.name.split(' ').slice(0, 2).map((part) => part[0]).join('').toUpperCase()
        : 'SH';

    const closeMenu = () => setMenuOpen(false);

    const logout = async () => {
        setLoggingOut(true);
        setLogoutError('');

        try {
            await csrfRequest({ method: 'post', url: '/log-out' });
            sessionStorage.removeItem('scenehub_user');
            navigate('/login', { replace: true });
        } catch (error) {
            setLogoutError(errorMessage(error, 'No fue posible cerrar la sesión. Inténtalo nuevamente.'));
            setLoggingOut(false);
        }
    };

    useEffect(() => {
        api.get('/session-user').then(({ data }) => {
            sessionStorage.setItem('scenehub_user', JSON.stringify(data));
            setUser(data);
        }).catch(() => {
            // La sesión se conserva desde el inicio de sesión; no mostrar una alerta por una consulta silenciosa.
        });
    }, []);

    return (
        <div className="app-shell">
            <header className="topbar">
                <button className="mobile-menu" type="button" onClick={() => setMenuOpen((value) => !value)} aria-label="Abrir menú">
                    {menuOpen ? <X size={22} /> : <Menu size={22} />}
                </button>
                <a className="wordmark" href="/dashboard">
                    <Boxes size={25} strokeWidth={2.1} />
                    <strong>SceneHub</strong>
                    <span>Plataforma de Integración y Comunicación</span>
                </a>
                <label className="global-search">
                    <Search size={18} />
                    <input type="search" placeholder="Buscar escenarios, versiones o archivos" aria-label="Buscar" />
                </label>
                <div className="topbar-actions">
                    <span className="secure-badge"><ShieldCheck size={17} /> Entorno seguro</span>
                    <button className="icon-button notification" type="button" aria-label="Notificaciones">
                        <Bell size={20} />
                        <i>3</i>
                    </button>
                    <span className="avatar">{initials}</span>
                    <span className="user-name">{user?.name || 'Usuario local'}</span>
                    <button className="logout-button" type="button" onClick={logout} disabled={loggingOut} title="Cerrar sesión">
                        <LogOut size={18} />
                        <span>{loggingOut ? 'Cerrando…' : 'Cerrar sesión'}</span>
                    </button>
                </div>
            </header>

            {logoutError && <div className="logout-error" role="alert">{logoutError}</div>}

            <aside className={`sidebar ${menuOpen ? 'sidebar--open' : ''}`}>
                <nav>
                    {mainNavigation.map(({ label, path, icon: Icon, faIcon }) => (
                        <NavLink key={path} to={path} onClick={closeMenu} className={({ isActive }) => isActive ? 'nav-link nav-link--active' : 'nav-link'}>
                            {Icon ? <Icon size={20} /> : <i className={`fa-solid ${faIcon} nav-fa-icon`} aria-hidden="true" />}
                            <span>{label}</span>
                        </NavLink>
                    ))}
                    {isAdmin && <p className="nav-title">Administración</p>}
                    {isAdmin && adminNavigation.map(({ label, path, icon: Icon }) => (
                        <NavLink key={path} to={path} onClick={closeMenu} className={({ isActive }) => isActive ? 'nav-link nav-link--active' : 'nav-link'}>
                            <Icon size={20} />
                            <span>{label}</span>
                        </NavLink>
                    ))}
                </nav>
                <div className="system-card">
                    <small>Estado del sistema</small>
                    <strong><span className="status-dot" /> Servicios disponibles</strong>
                    <p>Conexión local preparada</p>
                </div>
            </aside>

            {menuOpen && <button className="sidebar-scrim" type="button" onClick={closeMenu} aria-label="Cerrar menú" />}

            <main className="workspace">
                <div className="breadcrumbs"><span>{meta[0]}</span><i>/</i><strong>{meta[1]}</strong></div>
                {children}
            </main>
        </div>
    );
}
