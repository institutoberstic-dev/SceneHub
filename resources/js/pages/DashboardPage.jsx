import {
    ArrowUpRight,
    BarChart3,
    Boxes,
    CheckCircle2,
    Clock3,
    CloudCog,
    Database,
    FileArchive,
    Folder,
    FolderOpen,
    Plus,
    ShieldCheck,
    Users,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../http';

const moduleCards = [
    { title: 'Escenarios', text: 'Organiza cargas y archivos de simulación.', path: '/escenarios', icon: FolderOpen, tone: 'blue' },
    { title: 'Resultados', text: 'Consulta telemetría solar e hidrógeno.', path: '/resultados', icon: BarChart3, tone: 'green' },
    { title: 'Versiones', text: 'Prepara el historial de publicaciones.', path: '/versiones', icon: ShieldCheck, tone: 'purple' },
    { title: 'Caché local', text: 'Revisa la disponibilidad de contenidos.', path: '/cache-local', icon: Database, tone: 'cyan' },
];

export default function DashboardPage() {
    const [scenarios, setScenarios] = useState([]);
    const [metrics, setMetrics] = useState({ features: 0, solar: 0 });
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        Promise.allSettled([
            api.get('/api/escenarios'),
            api.get('/api/features'),
            api.get('/api/simu-solars'),
        ]).then(([scenarioResult, featureResult, solarResult]) => {
            if (scenarioResult.status === 'fulfilled') setScenarios(scenarioResult.value.data);
            setMetrics({
                features: featureResult.status === 'fulfilled' ? featureResult.value.data.length : 0,
                solar: solarResult.status === 'fulfilled' ? solarResult.value.data.length : 0,
            });
            setLoading(false);
        });
    }, []);

    return (
        <div className="page-stack">
            <section className="page-heading">
                <div>
                    <span className="eyebrow">Panel general</span>
                    <h1>Hub de escenarios</h1>
                    <p>Administra escenarios y consulta los resultados recibidos por la plataforma.</p>
                </div>
                <div className="heading-actions">
                    <Link className="primary-button" to="/escenarios"><Plus size={18} /> Nuevo escenario</Link>
                    <Link className="secondary-button" to="/resultados"><BarChart3 size={18} /> Ver resultados</Link>
                </div>
            </section>

            <section className="metric-grid">
                <article className="metric-card"><span className="metric-icon metric-icon--blue"><Folder size={21} /></span><div><small>Escenarios</small><strong>{scenarios.length}</strong><p>Registrados en el sistema</p></div></article>
                <article className="metric-card"><span className="metric-icon metric-icon--green"><Boxes size={21} /></span><div><small>Registros técnicos</small><strong>{metrics.features}</strong><p>Electrolizador e hidrógeno</p></div></article>
                <article className="metric-card"><span className="metric-icon metric-icon--purple"><CloudCog size={21} /></span><div><small>Simulaciones solares</small><strong>{metrics.solar}</strong><p>Lecturas recibidas</p></div></article>
                <article className="metric-card"><span className="metric-icon metric-icon--cyan"><CheckCircle2 size={21} /></span><div><small>Estado local</small><strong>Listo</strong><p>Servicios configurados</p></div></article>
            </section>

            <section>
                <div className="section-heading"><div><h2>Acceso rápido</h2><p>Módulos disponibles en esta etapa.</p></div></div>
                <div className="quick-grid">
                    {moduleCards.map(({ title, text, path, icon: Icon, tone }) => (
                        <Link className="quick-card" to={path} key={title}>
                            <span className={`folder-icon folder-icon--${tone}`}><Icon size={24} /></span>
                            <strong>{title}</strong>
                            <p>{text}</p>
                            <small>Abrir módulo <ArrowUpRight size={14} /></small>
                        </Link>
                    ))}
                </div>
            </section>

            <section className="content-card">
                <div className="section-heading section-heading--inside">
                    <div><h2>Escenarios recientes</h2><p>Información obtenida desde el controlador de escenarios.</p></div>
                    <Link to="/escenarios">Ver todos</Link>
                </div>
                <div className="table-wrap">
                    <table className="data-table">
                        <thead><tr><th>Nombre</th><th>Versiones</th><th>Estado</th><th>Actualización</th></tr></thead>
                        <tbody>
                            {loading && <tr><td colSpan="4"><div className="table-empty">Cargando información…</div></td></tr>}
                            {!loading && scenarios.length === 0 && <tr><td colSpan="4"><div className="table-empty"><FileArchive size={24} /> Aún no hay escenarios registrados.</div></td></tr>}
                            {scenarios.slice(0, 6).map((scenario) => (
                                <tr key={scenario.id}>
                                    <td><span className="name-cell"><Folder size={18} /> <strong>{scenario.nombre}</strong></span></td>
                                    <td>{scenario.versiones}</td>
                                    <td><span className="status-pill status-pill--green"><CheckCircle2 size={14} /> {scenario.estado}</span></td>
                                    <td><span className="muted-cell"><Clock3 size={15} /> {new Date(scenario.updated_at).toLocaleDateString('es-CO')}</span></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>

            <section className="admin-callout">
                <span><Users size={23} /></span>
                <div><strong>Administración preparada</strong><p>Gestiona usuarios y roles sin aplicar todavía middleware de acceso.</p></div>
                <Link to="/users-list">Ir a usuarios <ArrowUpRight size={16} /></Link>
            </section>
        </div>
    );
}
