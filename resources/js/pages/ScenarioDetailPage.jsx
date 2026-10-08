import { ArrowLeft, CheckCircle2, Clock3, Download, File, FileArchive, Folder, Info } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api, errorMessage } from '../http';
import Pagination from '../components/Pagination.jsx';
import { TechnologyChips } from '../components/TechnologySelector.jsx';

const typeLabels = { datos: 'Resultados importados', informe: 'Informe', documento: 'Documento' };
const fileIdentity = (item) => (item.ruta?.split('/').pop() || item.nombre).toLocaleLowerCase();

export default function ScenarioDetailPage() {
    const { id } = useParams();
    const [scenario, setScenario] = useState(null);
    const [loading, setLoading] = useState(true);
    const [feedback, setFeedback] = useState(null);
    const [tab, setTab] = useState('contents');
    const [contentPage, setContentPage] = useState(1);
    const [contentPageSize, setContentPageSize] = useState(15);
    const [versionPage, setVersionPage] = useState(1);
    const [versionPageSize, setVersionPageSize] = useState(10);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await api.get(`/escenarios-data/${id}`);
            setScenario(data);
        } catch (error) {
            setFeedback({ type: 'error', text: errorMessage(error, 'No fue posible abrir el escenario.') });
        } finally { setLoading(false); }
    }, [id]);

    useEffect(() => { load(); }, [load]);

    const versions = useMemo(() => {
        // El servidor entrega la instantánea real de cada carpeta de versión.
        if (Array.isArray(scenario?.versiones_contenido)) return scenario.versiones_contenido.map((entry) => [entry.version, entry.archivos]);
        const contents = scenario?.contenidos || [];
        const versionNumbers = [...new Set(contents.map((item) => Number(item.version)))]
            .sort((a, b) => a - b);
        const currentFiles = new Map();

        const snapshots = versionNumbers.map((version) => {
            contents
                .filter((item) => Number(item.version) === version)
                .sort((a, b) => new Date(a.updated_at) - new Date(b.updated_at))
                .forEach((item) => currentFiles.set(fileIdentity(item), item));

            return [String(version.toFixed(1)), [...currentFiles.values()]];
        });

        return snapshots.reverse();
    }, [scenario]);
    // Contenido = archivos de la versión vigente (nuevos y heredados), no todas las cargas.
    const contents = scenario?.contenido_actual || scenario?.contenidos || [];
    const visibleContents = contents.slice((contentPage - 1) * contentPageSize, contentPage * contentPageSize);
    const visibleVersions = versions.slice((versionPage - 1) * versionPageSize, versionPage * versionPageSize);

    if (loading) return <div className="loading-card">Cargando escenario…</div>;

    return (
        <div className="page-stack">
            <Link className="back-link" to="/escenarios"><ArrowLeft size={17} /> Volver a escenarios</Link>
            {feedback && <div className={`alert alert--${feedback.type}`}><Info size={18} />{feedback.text}</div>}
            {scenario && <>
                <section className="page-heading scenario-detail-heading">
                    <div><span className="eyebrow">Detalle del escenario{scenario.numero ? ` · N.º ${scenario.numero}` : ''}</span><h1>{scenario.nombre}</h1><p>{scenario.descripcion || 'Sin descripción'}</p><span className="muted-cell"><Folder size={15} /> Owner: {scenario.owner?.name || 'Sin owner'}</span><div className="scenario-technologies"><span className="scenario-technologies__label">Tecnologías participantes</span><TechnologyChips items={scenario.tecnologias || []} empty={<span className="muted-cell">Sin tecnologías asociadas</span>} /></div></div>
                    <a className="primary-button" href={`/escenarios/${scenario.id}/download`}><Download size={18} /> Descargar escenario (.zip)</a>
                </section>

                <section className="content-card">
                    <div className="detail-tabs" role="tablist"><button className={tab === 'contents' ? 'active' : ''} type="button" onClick={() => setTab('contents')}>Contenido ({contents.length}){scenario.version_vigente ? ` · V${scenario.version_vigente}` : ''}</button><button className={tab === 'versions' ? 'active' : ''} type="button" onClick={() => setTab('versions')}>Versiones ({versions.length})</button></div>
                    {tab === 'contents' && <div className="table-wrap"><table className="data-table"><thead><tr><th>Archivo</th><th>Tipo</th><th>Cargado en</th><th>Cargado por</th><th>Actualización</th><th /></tr></thead><tbody>
                        {visibleContents.map((item) => <tr key={item.id ?? item.nombre}><td><span className="name-cell"><File size={18} /><span className="name-cell__text"><strong>{item.nombre}</strong>{item.nombre_original && item.nombre_original !== item.nombre && <small title="Nombre con el que se subió">Subido como «{item.nombre_original}»</small>}</span></span></td><td><span className={`status-pill ${item.tipo === 'datos' ? 'status-pill--blue' : item.tipo === 'informe' ? 'status-pill--purple' : ''}`}>{typeLabels[item.tipo] || item.tipo}</span></td><td>V{item.version}</td><td>{item.uploaded_by?.name || 'Sistema'}</td><td><span className="muted-cell"><Clock3 size={14} />{new Date(item.updated_at).toLocaleString('es-CO')}</span></td><td>{item.id && <a className="icon-button" href={`/escenarios/${scenario.id}/contenidos/${item.id}/download`} title="Descargar archivo"><Download size={17} /></a>}</td></tr>)}
                        {contents.length === 0 && <tr><td colSpan="6"><div className="table-empty"><FileArchive size={24} /> Este escenario todavía no contiene archivos.</div></td></tr>}
                    </tbody></table>{contents.length > 0 && <Pagination total={contents.length} page={contentPage} pageSize={contentPageSize} onPageChange={setContentPage} onPageSizeChange={(size) => { setContentPageSize(size); setContentPage(1); }} />}</div>}
                    {tab === 'versions' && <div className="version-list">{visibleVersions.map(([version, items]) => <article className="version-card" key={version}><header><span><strong>Versión {version}</strong><small>{items.length} archivo(s)</small></span><span className="status-pill status-pill--green"><CheckCircle2 size={14} /> Disponible</span></header><div>{items.map((item) => <a href={`/escenarios/${scenario.id}/contenidos/${item.id}/download`} key={`${version}-${fileIdentity(item)}`}><File size={16} /><span className="version-file-info"><strong>{item.nombre}</strong><small><Clock3 size={13} /> Última modificación: {new Date(item.updated_at).toLocaleString('es-CO')}</small></span><Download size={15} /></a>)}</div></article>)}{versions.length === 0 && <div className="table-empty"><FileArchive size={24} /> No hay versiones registradas.</div>}{versions.length > 0 && <Pagination total={versions.length} page={versionPage} pageSize={versionPageSize} onPageChange={setVersionPage} onPageSizeChange={(size) => { setVersionPageSize(size); setVersionPage(1); }} />}</div>}
                </section>
            </>}
        </div>
    );
}
