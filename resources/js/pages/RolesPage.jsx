import { Info, KeyRound, Search, Shield, Users } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { api, errorMessage } from '../http';

const permissionLabels = {
    'usuarios.gestionar': 'Administrar usuarios',
    'roles.gestionar': 'Administrar accesos',
    'escenarios.leer': 'Acceso a Escenarios',
    'emociones.leer': 'Acceso a Emociones',
};

export default function RolesPage() {
    const [roles, setRoles] = useState([]);
    const [loading, setLoading] = useState(true);
    const [query, setQuery] = useState('');
    const [failure, setFailure] = useState('');

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await api.get('/roles-data');
            setRoles(data);
        } catch (error) {
            setFailure(errorMessage(error, 'No fue posible consultar los roles.'));
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => { load(); }, [load]);

    const visibleRoles = useMemo(() => roles.filter((role) => role.name.toLowerCase().includes(query.toLowerCase())), [query, roles]);

    return (
        <div className="page-stack">
            <section className="page-heading"><div><span className="eyebrow">Administración</span><h1>Roles y permisos</h1><p>Los roles definen el alcance global; los módulos se habilitan individualmente desde Usuarios.</p></div></section>
            {failure && <div className="alert alert--error" role="alert"><Info size={18} />{failure}</div>}
            <div className="info-banner"><Info size={19} /><div><strong>Modelo de acceso simplificado</strong><p>Administrador tiene control completo. Usuario recibe acceso a Escenarios, Emociones o ambos desde su formulario de registro o actualización.</p></div></div>
            <section className="content-card">
                <div className="toolbar"><label className="inline-search"><Search size={17} /><input placeholder="Buscar rol" value={query} onChange={(event) => setQuery(event.target.value)} /></label></div>
                <div className="role-grid">
                    {visibleRoles.map((role, index) => {
                        const isAdmin = role.name === 'admin';
                        const highlightedPermissions = isAdmin
                            ? role.permissions?.filter((permission) => permissionLabels[permission.name])
                            : [];
                        return <article className="role-card role-card--access" key={role.id}>
                            <header><span className={`metric-icon metric-icon--${index ? 'purple' : 'blue'}`}><Shield size={21} /></span><span className="status-pill status-pill--blue">Rol del sistema</span></header>
                            <h2>{isAdmin ? 'Administrador' : 'Usuario'}</h2>
                            <p>{isAdmin ? 'Control total de cuentas, accesos y supervisión de los módulos.' : 'Accede únicamente a los módulos habilitados por un administrador.'}</p>
                            {isAdmin && <div className="role-permissions">{highlightedPermissions.map((permission) => <span key={permission.id}><KeyRound size={13} />{permissionLabels[permission.name]}</span>)}</div>}
                            {!isAdmin && <div className="role-permissions"><span><KeyRound size={13} />Permisos asignados por usuario</span></div>}
                            <footer><span><Users size={16} /> {role.users_count} usuarios</span><span><KeyRound size={16} /> {isAdmin ? role.permissions_count : 'Variable'} permisos</span></footer>
                        </article>;
                    })}
                    {loading && <div className="loading-card">Cargando roles…</div>}
                    {!loading && visibleRoles.length === 0 && <div className="table-empty"><Shield size={24} /> No hay roles para mostrar.</div>}
                </div>
            </section>
        </div>
    );
}
