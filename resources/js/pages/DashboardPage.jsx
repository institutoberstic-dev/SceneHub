import { BarChart3, CheckCircle2, Clock3, File, Folder, FolderOpen, Plus, ShieldCheck, UploadCloud } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../http';

const tones = ['blue', 'green', 'purple', 'cyan'];

function formatSize(bytes = 0) {
    if (!bytes) return '—';
    if (bytes < 1024 * 1024) return `${Math.ceil(bytes / 1024)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export default function DashboardPage() {
    let currentUser = null;
    try { currentUser = JSON.parse(sessionStorage.getItem('scenehub_user') || 'null'); } catch { /* La sesión será validada por Laravel. */ }
    const isAdmin = currentUser?.roles?.includes('admin');
    const canAccessScenarios = isAdmin || currentUser?.permissions?.includes('escenarios.leer');
    const canAccessEmotions = isAdmin || currentUser?.permissions?.includes('emociones.leer');
    const [scenarios, setScenarios] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (!canAccessScenarios) {
            setLoading(false);
            return;
        }
        api.get('/escenarios-data')
            .then(({ data }) => setScenarios(data))
            .finally(() => setLoading(false));
    }, [canAccessScenarios]);

    const contents = useMemo(
        () => scenarios.flatMap((scenario) => (scenario.contenidos || []).map((item) => ({ ...item, scenario }))),
        [scenarios],
    );
    const versions = scenarios.reduce((total, scenario) => total + Number(scenario.versiones || 0), 0);
    const quickItems = contents.slice(0, 4);

    if (!canAccessScenarios) {
        return <div className="page-stack">
            <section className="page-heading"><div><span className="eyebrow">Inicio</span><h1>Módulos disponibles</h1><p>Accede a los espacios habilitados por el administrador.</p></div></section>
            <section className="content-card module-welcome">
                {canAccessEmotions
                    ? <><span className="metric-icon metric-icon--purple"><BarChart3 size={22} /></span><div><h2>Emociones</h2><p>Consulta webinars y resultados emocionales disponibles.</p><Link className="primary-button" to="/emociones">Abrir módulo</Link></div></>
                    : <><span className="metric-icon"><ShieldCheck size={22} /></span><div><h2>Sin módulos habilitados</h2><p>Solicita al administrador acceso a Escenarios o Emociones.</p></div></>}
            </section>
        </div>;
    }

    return (
        <div className="page-stack">
            <section className="page-heading">
                <div><span className="eyebrow">Inicio</span><h1>Hub de escenarios</h1><p>Administra tus carpetas, escenarios y paquetes de simulación disponibles.</p></div>
                <div className="heading-actions"><Link className="primary-button" to="/escenarios"><Plus size={18} /> Nueva carpeta</Link><Link className="secondary-button" to="/escenarios"><UploadCloud size={18} /> Subir archivos</Link></div>
            </section>

            <section className="metric-grid">
                <article className="metric-card"><span className="metric-icon metric-icon--blue"><FolderOpen size={21} /></span><div><small>Escenarios</small><strong>{scenarios.length}</strong><p>Carpetas accesibles</p></div></article>
                <article className="metric-card"><span className="metric-icon metric-icon--green"><File size={21} /></span><div><small>Contenidos</small><strong>{contents.length}</strong><p>Archivos y paquetes cargados</p></div></article>
                <article className="metric-card"><span className="metric-icon metric-icon--purple"><Clock3 size={21} /></span><div><small>Versiones</small><strong>{versions}</strong><p>Registradas en tus escenarios</p></div></article>
                <article className="metric-card"><span className="metric-icon metric-icon--cyan"><CheckCircle2 size={21} /></span><div><small>Disponibles</small><strong>{contents.filter((item) => item.estado === 'Disponible').length}</strong><p>Listos para trabajar</p></div></article>
            </section>

            <section>
                <div className="section-heading"><div><h2>Acceso rápido</h2><p>Contenido actualizado recientemente dentro de tus escenarios.</p></div></div>
                <div className="quick-grid">
                    {quickItems.map((item, index) => <Link className="quick-card" to="/escenarios" key={item.id}><span className={`folder-icon folder-icon--${tones[index % tones.length]}`}><Folder size={24} /></span><strong>{item.nombre}</strong><p>{item.scenario.nombre} · {item.tipo}</p><small>{formatSize(item.tamano)} · V{item.version}</small></Link>)}
                    {!loading && quickItems.length === 0 && <article className="quick-card quick-card--empty"><span className="folder-icon"><Folder size={24} /></span><strong>Sin contenido cargado</strong><p>Los archivos que subas a un escenario aparecerán aquí.</p></article>}
                </div>
            </section>

            <section className="content-card">
                <div className="section-heading section-heading--inside"><div><h2>Mis carpetas y archivos</h2><p>Elementos internos disponibles en los escenarios a los que tienes acceso.</p></div><Link to="/escenarios">Gestionar escenarios</Link></div>
                <div className="table-wrap"><table className="data-table"><thead><tr><th>Nombre</th><th>Tipo</th><th>Escenario</th><th>Última actualización</th><th>Versión</th><th>Estado</th></tr></thead><tbody>
                    {contents.slice(0, 8).map((item) => <tr key={item.id}><td><span className="name-cell"><File size={18} /><strong>{item.nombre}</strong></span></td><td>{item.tipo}</td><td>{item.scenario.nombre}</td><td>{new Date(item.updated_at).toLocaleString('es-CO')}</td><td>V{item.version}</td><td><span className="status-pill status-pill--green"><CheckCircle2 size={14} />{item.estado}</span></td></tr>)}
                    {loading && <tr><td colSpan="6"><div className="table-empty">Cargando contenidos…</div></td></tr>}
                    {!loading && contents.length === 0 && <tr><td colSpan="6"><div className="table-empty"><Folder size={24} /> Aún no hay archivos dentro de tus escenarios.</div></td></tr>}
                </tbody></table></div>
            </section>
        </div>
    );
}
