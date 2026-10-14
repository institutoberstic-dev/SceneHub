import { Archive, ArrowLeft, CheckCircle2, Download, FileArchive, History, Info, Layers, ListChecks, Send, ShieldCheck, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import Modal from '../components/Modal';
import TerritoryUploader from '../components/TerritoryUploader.jsx';
import { accionesRegistro, estados, fecha, peorResultado, resultados, tamanoLegible } from '../components/territorio';
import { api, csrfRequest, errorList, errorMessage } from '../http';

function FeedbackAlert({ feedback }) {
    if (!feedback) return null;
    return <div className={`alert alert--${feedback.type}`} role={feedback.type === 'error' ? 'alert' : 'status'}>{feedback.type === 'success' ? <CheckCircle2 size={18} /> : <Info size={18} />}<div className="alert__body"><span>{feedback.text}</span>{feedback.items?.length > 1 && <ul className="alert__list">{feedback.items.map((item) => <li key={item}>{item}</li>)}</ul>}</div></div>;
}

const ResultadoPill = ({ resultado }) => <span className={`status-pill ${resultados[resultado]?.tone || ''}`}>{resultados[resultado]?.label || resultado}</span>;

export default function TerritoryDetailPage() {
    const { id } = useParams();
    const [paquete, setPaquete] = useState(null);
    const [loading, setLoading] = useState(true);
    const [feedback, setFeedback] = useState(null);
    const [tab, setTab] = useState('archivos');
    const [busy, setBusy] = useState(false);
    const [confirmPublish, setConfirmPublish] = useState(false);
    const [retiro, setRetiro] = useState(null);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await api.get(`/territorio-data/${id}`);
            setPaquete(data);
        } catch (error) {
            setFeedback({ type: 'error', text: errorMessage(error, 'No fue posible abrir el paquete.') });
        } finally { setLoading(false); }
    }, [id]);

    useEffect(() => { load(); }, [load]);

    const porArchivo = useMemo(() => {
        const grupos = new Map();
        (paquete?.validaciones || []).forEach((v) => {
            const clave = v.archivo_id ?? 'paquete';
            grupos.set(clave, [...(grupos.get(clave) || []), v]);
        });
        return grupos;
    }, [paquete]);

    const accion = async (config, exito) => {
        setBusy(true);
        setFeedback(null);
        try {
            const { data } = await csrfRequest(config);
            setPaquete(data.data);
            setFeedback({ type: 'success', text: data.message || exito });
            return true;
        } catch (error) {
            const items = errorList(error);
            setFeedback({ type: 'error', text: items[0], items });
            return false;
        } finally { setBusy(false); }
    };

    if (loading) return <div className="loading-card">Cargando paquete…</div>;
    if (!paquete) return <div className="page-stack"><Link className="back-link" to="/territorio"><ArrowLeft size={17} /> Volver a territorio</Link><FeedbackAlert feedback={feedback} /></div>;

    const { puede } = paquete;
    const nombreArchivo = (archivoId) => paquete.archivos.find((a) => a.id === archivoId)?.nombre_original || 'Archivo reemplazado';

    return (
        <div className="page-stack">
            <Link className="back-link" to="/territorio"><ArrowLeft size={17} /> Volver a territorio</Link>
            <FeedbackAlert feedback={feedback} />

            <section className="page-heading">
                <div>
                    <span className="eyebrow">Paquete territorial · PT1 / GAST</span>
                    <h1>Versión {paquete.version} <span className={`status-pill ${estados[paquete.estado]?.tone || ''}`}>{estados[paquete.estado]?.label || paquete.estado}</span></h1>
                    <p>{paquete.notas || 'Sin notas.'}</p>
                    <span className="muted-cell">Creado por {paquete.creado_por || '—'} · {fecha(paquete.creado_en)}{paquete.publicado_en ? ` · Publicado por ${paquete.publicado_por || '—'} el ${fecha(paquete.publicado_en)}` : ''}</span>
                </div>
                <div className="heading-actions">
                    {puede.validar && <button className="secondary-button" type="button" disabled={busy} onClick={() => accion({ method: 'post', url: `/territorio-paquetes/${paquete.id}/validar` })}><ListChecks size={18} /> Validar paquete</button>}
                    {puede.publicar && <button className="primary-button" type="button" disabled={busy || paquete.bloqueos > 0} title={paquete.bloqueos > 0 ? 'Resuelva los errores y confirme las advertencias antes de publicar.' : ''} onClick={() => setConfirmPublish(true)}><Send size={18} /> Publicar</button>}
                    {puede.retirar && <button className="danger-button" type="button" disabled={busy} onClick={() => setRetiro('')}><Archive size={18} /> Retirar</button>}
                </div>
            </section>

            {paquete.estado === 'publicado' && <div className="info-banner"><ShieldCheck size={20} /><span>Versión publicada: es <strong>inmutable</strong>. Para corregir algo, cree una versión nueva. La app «Puente de datos» ya puede copiarla.</span></div>}
            {paquete.estado === 'retirado' && <div className="info-banner"><Archive size={20} /><span>Versión retirada el {fecha(paquete.retirado_en)}. Motivo: {paquete.motivo_retiro}. Se conserva como historial.</span></div>}
            {paquete.estado === 'validado' && <div className="info-banner"><Info size={20} /><span>Validado sin errores. {paquete.bloqueos > 0 ? `Quedan ${paquete.bloqueos} advertencia(s) por confirmar antes de publicar.` : 'Listo para la revisión y la publicación.'}</span></div>}

            <section className="content-card">
                <div className="detail-tabs" role="tablist">
                    <button className={tab === 'archivos' ? 'active' : ''} type="button" onClick={() => setTab('archivos')}>Archivos ({paquete.archivos.length})</button>
                    <button className={tab === 'validaciones' ? 'active' : ''} type="button" onClick={() => setTab('validaciones')}>Validaciones{paquete.bloqueos > 0 ? ` (${paquete.bloqueos} pendientes)` : ''}</button>
                    <button className={tab === 'registro' ? 'active' : ''} type="button" onClick={() => setTab('registro')}>Actividad</button>
                </div>

                {tab === 'archivos' && <>
                    {puede.subir && <TerritoryUploader paquete={paquete} onUploaded={(data) => { setPaquete(data.data); setFeedback({ type: 'success', text: data.message }); }} onError={(items) => setFeedback({ type: 'error', text: items[0], items })} />}
                    <div className="table-wrap">
                        <table className="data-table">
                            <thead><tr><th>Tipo</th><th>Archivo</th><th>Tamaño</th><th>SHA-256</th><th>CRS</th><th>Validación</th><th>Subido</th><th /></tr></thead>
                            <tbody>
                                {paquete.archivos.map((a) => (
                                    <tr key={a.id}>
                                        <td><span className="status-pill status-pill--purple">{a.tipo_nombre}</span></td>
                                        <td><span className="name-cell"><Layers size={18} /><span className="name-cell__text"><strong>{a.nombre_original}</strong><small>{[a.capa && `Capa ${a.capa}`, a.fuente, a.licencia && `Licencia: ${a.licencia}`, a.es_sensible && 'Sensible'].filter(Boolean).join(' · ') || a.descripcion || ''}</small></span></span></td>
                                        <td>{tamanoLegible(a.tamano)}</td>
                                        <td><code title={a.sha256}>{a.sha256.slice(0, 12)}…</code></td>
                                        <td>{a.crs || '—'}</td>
                                        <td><ResultadoPill resultado={peorResultado(porArchivo.get(a.id) || [])} /></td>
                                        <td><span className="muted-cell">{a.subido_por || '—'} · {fecha(a.subido_en)}</span></td>
                                        <td className="row-actions">
                                            <a className="icon-button" href={`/territorio-paquetes/${paquete.id}/archivos/${a.id}/descargar`} title="Descargar"><Download size={17} /></a>
                                            {puede.subir && <button className="icon-button icon-button--danger" type="button" disabled={busy} title="Quitar del borrador" onClick={() => accion({ method: 'delete', url: `/territorio-paquetes/${paquete.id}/archivos/${a.id}` })}><Trash2 size={17} /></button>}
                                        </td>
                                    </tr>
                                ))}
                                {paquete.archivos.length === 0 && <tr><td colSpan="8"><div className="table-empty"><FileArchive size={24} /> Este paquete todavía no tiene archivos.</div></td></tr>}
                            </tbody>
                        </table>
                    </div>
                </>}

                {tab === 'validaciones' && <div className="territory-validations">
                    {[...porArchivo.entries()].map(([clave, items]) => (
                        <article className="version-card" key={clave}>
                            <header><span><strong>{clave === 'paquete' ? 'Paquete completo' : nombreArchivo(clave)}</strong><small>{items.length} control(es)</small></span><ResultadoPill resultado={peorResultado(items)} /></header>
                            <ul className="validation-list">
                                {items.map((v) => (
                                    <li key={v.id} className={`validation-item validation-item--${v.resultado}`}>
                                        <ResultadoPill resultado={v.resultado} />
                                        <span className="validation-item__text">{v.detalle}<small>{v.regla}</small></span>
                                        {v.requiere_confirmacion && (v.confirmada_en
                                            ? <small className="muted-cell"><CheckCircle2 size={14} /> Confirmada por {v.confirmada_por || '—'}</small>
                                            : puede.confirmar && <button className="secondary-button" type="button" disabled={busy} onClick={() => accion({ method: 'post', url: `/territorio-paquetes/${paquete.id}/validaciones/${v.id}/confirmar` })}>Confirmar</button>)}
                                    </li>
                                ))}
                            </ul>
                        </article>
                    ))}
                    {porArchivo.size === 0 && <div className="table-empty"><ListChecks size={24} /> Aún no hay validaciones: suba archivos y pulse «Validar paquete».</div>}
                </div>}

                {tab === 'registro' && <div className="table-wrap">
                    <table className="data-table">
                        <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Detalle</th></tr></thead>
                        <tbody>
                            {paquete.registro.map((r, i) => <tr key={`${r.fecha}-${i}`}><td>{fecha(r.fecha)}</td><td>{r.usuario || '—'}</td><td>{accionesRegistro[r.accion] || r.accion}</td><td>{r.detalle}</td></tr>)}
                            {paquete.registro.length === 0 && <tr><td colSpan="4"><div className="table-empty"><History size={24} /> Sin actividad.</div></td></tr>}
                        </tbody>
                    </table>
                </div>}
            </section>

            <Modal open={confirmPublish} onClose={() => setConfirmPublish(false)} title={`¿Publicar la versión ${paquete.version}?`} subtitle="Al publicarla queda inmutable: no se podrán cambiar ni borrar sus archivos. Si algo está mal, se retira y se publica una versión nueva.">
                <div className="modal-form">
                    <p>{paquete.archivos.length} archivo(s): {paquete.archivos.map((a) => a.tipo_nombre).join(', ')}.</p>
                    <div className="modal-actions"><button className="secondary-button" type="button" onClick={() => setConfirmPublish(false)}>Cancelar</button><button className="primary-button" type="button" disabled={busy} onClick={async () => { if (await accion({ method: 'post', url: `/territorio-paquetes/${paquete.id}/publicar` })) setConfirmPublish(false); }}><Send size={18} /> Sí, publicar</button></div>
                </div>
            </Modal>

            <Modal open={retiro !== null} onClose={() => setRetiro(null)} title={`Retirar la versión ${paquete.version}`} subtitle="La versión no se borra: queda como historial y deja de ser la vigente.">
                <form className="modal-form" onSubmit={async (event) => { event.preventDefault(); if (await accion({ method: 'post', url: `/territorio-paquetes/${paquete.id}/retirar`, data: { motivo: retiro } })) setRetiro(null); }}>
                    <label className="field-label" htmlFor="territorio-motivo">Motivo del retiro</label>
                    <textarea className="text-input text-area" id="territorio-motivo" required minLength="10" maxLength="2000" value={retiro || ''} onChange={(event) => setRetiro(event.target.value)} />
                    <div className="modal-actions"><button className="secondary-button" type="button" onClick={() => setRetiro(null)}>Cancelar</button><button className="danger-button" type="submit" disabled={busy}><Archive size={18} /> Retirar versión</button></div>
                </form>
            </Modal>
        </div>
    );
}
