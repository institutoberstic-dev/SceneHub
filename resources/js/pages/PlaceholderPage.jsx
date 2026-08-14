import { Archive, ArrowRight, CheckCircle2, CloudCog, Database, FileClock, Info, RefreshCw, ShieldCheck } from 'lucide-react';
import { Link } from 'react-router-dom';

const content = {
    versions: { eyebrow: 'Historial técnico', title: 'Versiones', description: 'Espacio preparado para consultar y publicar versiones consecutivas de cada escenario.', icon: ShieldCheck, steps: ['Seleccionar escenario', 'Revisar contenido', 'Validar paquete', 'Publicar versión'] },
    sync: { eyebrow: 'Integración', title: 'Sincronización', description: 'Vista preparada para mostrar el estado de intercambio entre simulación y visualización.', icon: CloudCog, steps: ['Detectar cambios', 'Comparar versión', 'Transferir paquete', 'Confirmar integridad'] },
    cache: { eyebrow: 'Disponibilidad', title: 'Caché local', description: 'Módulo visual para la futura administración de contenido disponible sin conexión.', icon: Database, steps: ['Seleccionar contenido', 'Descargar paquete', 'Verificar checksum', 'Marcar disponible'] },
};

export default function PlaceholderPage({ type }) {
    const page = content[type];
    const Icon = page.icon;
    return (
        <div className="page-stack">
            <section className="page-heading"><div><span className="eyebrow">{page.eyebrow}</span><h1>{page.title}</h1><p>{page.description}</p></div><span className="status-pill status-pill--blue"><Info size={15} /> Diseño API preparado</span></section>
            <section className="placeholder-hero"><span className="placeholder-hero__icon"><Icon size={38} /></span><div><span className="eyebrow">Módulo en preparación</span><h2>Interfaz lista para conectar la operación</h2><p>El controlador devuelve esta vista, pero no se ejecutan procesos reales porque el flujo backend todavía no está definido.</p></div></section>
            <section className="content-card process-card"><div className="section-heading section-heading--inside"><div><h2>Flujo previsto</h2><p>Representación visual sin acciones persistentes.</p></div></div><div className="process-line">{page.steps.map((step, index) => <div key={step}><span>{index + 1}</span><strong>{step}</strong>{index < page.steps.length - 1 && <ArrowRight size={18} />}</div>)}</div></section>
            <section className="placeholder-grid"><article><Archive size={23} /><strong>Sin operaciones destructivas</strong><p>No se crean, actualizan ni eliminan registros desde este módulo.</p></article><article><FileClock size={23} /><strong>Contrato pendiente</strong><p>La interfaz puede conectarse cuando se definan modelos y endpoints.</p></article><article><CheckCircle2 size={23} /><strong>Estilo consistente</strong><p>Conserva navegación, estados y componentes del sistema.</p></article></section>
            <div className="info-banner"><RefreshCw size={19} /><div><strong>Siguiente paso recomendado</strong><p>Definir con el equipo qué datos y operaciones debe proporcionar este módulo.</p></div><Link to="/dashboard">Volver al inicio</Link></div>
        </div>
    );
}
