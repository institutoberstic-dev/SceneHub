import {
    BarChart3,
    Bell,
    Boxes,
    CloudCog,
    Database,
    FolderOpen,
    Home,
    Menu,
    Search,
    ShieldCheck,
    Users,
    UserRoundCog,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { NavLink, useLocation } from 'react-router-dom';

const mainNavigation = [
    { label: 'Inicio', path: '/dashboard', icon: Home },
    { label: 'Escenarios', path: '/escenarios', icon: FolderOpen },
    { label: 'Resultados', path: '/resultados', icon: BarChart3 },
    { label: 'Versiones', path: '/versiones', icon: ShieldCheck },
    { label: 'Sincronización', path: '/sincronizacion', icon: CloudCog },
    { label: 'Caché local', path: '/cache-local', icon: Database },
];

const adminNavigation = [
    { label: 'Usuarios', path: '/users-list', icon: Users },
    { label: 'Roles', path: '/roles-list', icon: UserRoundCog },
];

const pageMeta = {
    '/dashboard': ['Inicio', 'Hub de escenarios'],
    '/escenarios': ['Escenarios', 'Carga y administración'],
    '/resultados': ['Resultados', 'Telemetría recibida'],
    '/versiones': ['Versiones', 'Historial de publicaciones'],
    '/sincronizacion': ['Sincronización', 'Estado de integración'],
    '/cache-local': ['Caché local', 'Disponibilidad de contenidos'],
    '/users-list': ['Administración', 'Usuarios'],
    '/users-create': ['Administración', 'Usuarios'],
    '/roles-list': ['Administración', 'Roles'],
    '/roles-create': ['Administración', 'Roles'],
};

export default function AppLayout({ children }) {
    const [menuOpen, setMenuOpen] = useState(false);
    const location = useLocation();
    const meta = pageMeta[location.pathname] || ['SceneHub', 'Módulo'];
    const user = JSON.parse(sessionStorage.getItem('scenehub_user') || 'null');
    const initials = user?.name
        ? user.name.split(' ').slice(0, 2).map((part) => part[0]).join('').toUpperCase()
        : 'SH';

    const closeMenu = () => setMenuOpen(false);

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
                </div>
            </header>

            <aside className={`sidebar ${menuOpen ? 'sidebar--open' : ''}`}>
                <nav>
                    {mainNavigation.map(({ label, path, icon: Icon }) => (
                        <NavLink key={path} to={path} onClick={closeMenu} className={({ isActive }) => isActive ? 'nav-link nav-link--active' : 'nav-link'}>
                            <Icon size={20} />
                            <span>{label}</span>
                        </NavLink>
                    ))}
                    <p className="nav-title">Administración</p>
                    {adminNavigation.map(({ label, path, icon: Icon }) => (
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
