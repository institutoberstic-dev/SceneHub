import { Archive, Check, CheckCircle2, Copy, Download, Info, MonitorSmartphone, ShieldCheck, UploadCloud, XCircle } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import Modal from '../components/Modal';
import { comandoVerificacion, siguienteVersion, tipoPorArchivo } from '../components/puenteApp';
import { fecha, tamanoLegible } from '../components/territorio';
import { api, csrfRequest, errorList, errorMessage } from '../http';

function FeedbackAlert({ feedback }) {
    if (!feedback) return null;
    return <div className={`alert alert--${feedback.type}`} role={feedback.type === 'error' ? 'alert' : 'status'}>{feedback.type === 'success' ? <CheckCircle2 size={18} /> : <Info size={18} />}<div className="alert__body"><span>{feedback.text}</span>{feedback.items?.length > 1 && <ul className="alert__list">{feedback.items.map((item) => <li key={item}>{item}</li>)}</ul>}</div></div>;
}

function Copiar({ texto, etiqueta }) {
    const [copiado, setCopiado] = useState(false);
    const copiar = async () => {
        try {
            await navigator.clipboard.writeText(texto);
            setCopiado(true);
            setTimeout(() => setCopiado(false), 1600);
        } catch { /* Sin portapapeles: el texto sigue visible para copiarlo a mano. */ }
    };
    return <button className="secondary-button puente-copy" type="button" onClick={copiar} aria-label={etiqueta}>{copiado ? <Check size={15} /> : <Copy size={15} />} {copiado ? 'Copiado' : 'Copiar'}</button>;
}

const formularioVacio = (version = '1.0.0') => ({ version, tipo: 'instalador', notas: '', archivo: null });

export default function PuenteAppPage() {
    const [data, setData] = useState(null);
    const [feedback, setFeedback] = useState(null);
    const [form, setForm] = useState(formularioVacio());
    const [progreso, setProgreso] = useState(null);
    const [arrastrando, setArrastrando] = useState(false);
    const [retiro, setRetiro] = useState(null);
    const [motivo, setMotivo] = useState('');

    const load = useCallback(async () => {
        try {
            const { data: respuesta } = await api.get('/puente-app-data');
            setData(respuesta);
            setForm((actual) => (actual.archivo ? actual : formularioVacio(siguienteVersion(respuesta.versiones))));
        } catch (error) {
            setFeedback({ type: 'error', text: errorMessage(error, 'No fue posible consultar las versiones del Puente de datos.') });
        }
    }, []);

    useEffect(() => { load(); }, [load]);

    const elegir = (archivo) => {
        if (!archivo) return;
        setForm((actual) => ({ ...actual, archivo, tipo: tipoPorArchivo(archivo.name) || actual.tipo }));
    };

    const publicar = async (event) => {
        event.preventDefault();
        if (!form.archivo) return;
        const cuerpo = new FormData();
        Object.entries(form).forEach(([clave, valor]) => { if (valor) cuerpo.append(clave, valor); });
        setProgreso(0);
        setFeedback(null);
        try {
            const { data: respuesta } = await csrfRequest({
                method: 'post',
                url: '/puente-app/versiones',
                data: cuerpo,
                onUploadProgress: (e) => e.total && setProgreso(Math.round((e.loaded * 100) / e.total)),
            });
            setForm(formularioVacio());
            setFeedback({ type: 'success', text: respuesta.message });
            await load();
        } catch (error) {
            const items = errorList(error, 'No fue posible publicar la versión.');
            setFeedback({ type: 'error', text: items[0], items });
        } finally { setProgreso(null); }
    };

    const retirar = async (event) => {
        event.preventDefault();
        try {
            const { data: respuesta } = await csrfRequest({ method: 'post', url: `/puente-app/versiones/${retiro.id}/retirar`, data: { motivo } });
            setRetiro(null);
            setMotivo('');
            setFeedback({ type: 'success', text: respuesta.message });
            await load();
        } catch (error) {
            const items = errorList(error, 'No fue posible retirar la versión.');
            setFeedback({ type: 'error', text: items[0], items });
        }
    };

    if (!data) return feedback ? <FeedbackAlert feedback={feedback} /> : <div className="loading-card">Cargando el Puente de datos…</div>;

    const tipoElegido = data.tipos.find((t) => t.clave === form.tipo);

    return (
        <div className="page-stack">
            <section className="page-heading">
                <div>
                    <span className="eyebrow">Aplicación de escritorio · GuajiraTech → ANH</span>
                    <h1>Puente de datos</h1>
                    <p>Descargue la aplicación que copia, revisa y entrega a la base de la ANH lo publicado en SceneHub: resultados de escenarios y el paquete territorial. Se instala en el PC del proyecto y funciona en modo manual.</p>
                </div>
            </section>

            {!retiro && <FeedbackAlert feedback={feedback} />}

            <section className="puente-grid">
                {data.vigentes.map((v) => (
                    <article key={v.id} className="content-card puente-release">
                        <header>
                            <span className="puente-release__icon">{v.tipo === 'portable' ? <Archive size={22} /> : <MonitorSmartphone size={22} />}</span>
                            <div>
                                <small>{v.tipo_nombre}{v.tipo === 'instalador' ? ' · recomendado' : ''}</small>
                                <h2>Versión {v.version}</h2>
                            </div>
                            <span className="status-pill status-pill--green">Vigente</span>
                        </header>
                        <dl className="puente-release__datos">
                            <div><dt>Publicada</dt><dd>{fecha(v.publicada_en)}</dd></div>
                            <div><dt>Tamaño</dt><dd>{tamanoLegible(v.tamano)}</dd></div>
                        </dl>
                        {v.notas && <p className="puente-release__notas">{v.notas}</p>}
                        <a className="primary-button puente-release__descargar" href={v.url_descarga} download={v.nombre_descarga}><Download size={18} /> Descargar {v.nombre_descarga}</a>
                        <details className="puente-release__hash">
                            <summary><ShieldCheck size={15} /> Comprobar que el archivo llegó completo (SHA-256)</summary>
                            <code>{v.sha256}</code>
                            <p>En PowerShell, después de descargarlo, este comando debe mostrar exactamente la misma huella:</p>
                            <code>{comandoVerificacion(v.nombre_descarga)}</code>
                            <Copiar texto={comandoVerificacion(v.nombre_descarga)} etiqueta="Copiar comando de verificación" />
                        </details>
                    </article>
                ))}
                {data.vigentes.length === 0 && (
                    <article className="content-card puente-release puente-release--vacia">
                        <Download size={26} />
                        <strong>Todavía no hay una versión publicada.</strong>
                        <span>{data.puede_publicar ? 'Publique la primera en «Publicar una versión», más abajo.' : 'Pida a quien administra SceneHub que publique el instalador.'}</span>
                    </article>
                )}
            </section>

            <section className="content-card puente-pasos">
                <h2>Instalar y conectar</h2>
                <ol>
                    <li><strong>Descargue el instalador</strong> y ábralo. No pide permisos de administrador: se instala para su usuario y crea un acceso en el menú Inicio.
                        <small>Si Windows muestra «Windows protegió su PC», elija «Más información» → «Ejecutar de todas formas» (el instalador aún no está firmado digitalmente).</small></li>
                    <li><strong>Abra «Puente de datos»</strong> → <em>Configuración</em> y escriba la dirección de SceneHub:
                        <span className="puente-valor"><code>{data.url_scenehub}</code><Copiar texto={data.url_scenehub} etiqueta="Copiar dirección de SceneHub" /></span></li>
                    <li><strong>Pegue el token de solo lectura</strong> y pulse <em>Guardar</em>. Lo entrega quien administra SceneHub (<code>php artisan puente:token-crear "Nombre del PC" --dias=365</code>) y se muestra una sola vez; la app lo guarda en el Administrador de credenciales de Windows.</li>
                    <li><strong>«Probar conexión»</strong> debe mostrar ✓ SceneHub. Luego, en <em>Inicio</em>, «Buscar novedades».</li>
                </ol>
                <p className="puente-pasos__nota"><Info size={15} /> {data.requisitos} Al actualizar, instale la versión nueva encima: la configuración y el token se conservan.</p>
            </section>

            {data.puede_publicar && (
                <section className="content-card puente-publicar">
                    <h2>Publicar una versión</h2>
                    <p>Suba el instalador generado con <code>scripts\empaquetar.ps1</code> en el repositorio PuentePython (queda en <code>dist\entrega\</code>). Máximo {data.max_mb} MB.</p>
                    <form onSubmit={publicar}>
                        <label
                            className={`upload-zone${arrastrando ? ' upload-zone--active' : ''}`}
                            htmlFor="puente-archivo"
                            onDragOver={(e) => { e.preventDefault(); setArrastrando(true); }}
                            onDragLeave={() => setArrastrando(false)}
                            onDrop={(e) => { e.preventDefault(); setArrastrando(false); elegir(e.dataTransfer.files?.[0]); }}
                        >
                            <UploadCloud size={30} />
                            <strong>{form.archivo ? `${form.archivo.name} · ${tamanoLegible(form.archivo.size)}` : 'Arrastre aquí el instalador (.exe) o el portable (.zip), o haga clic para elegirlo'}</strong>
                            <span>{tipoElegido ? `${tipoElegido.nombre}: .${tipoElegido.extensiones.join(', .')}` : ''}</span>
                            <input id="puente-archivo" type="file" accept=".exe,.msi,.zip" onChange={(e) => elegir(e.target.files?.[0])} />
                        </label>
                        <div className="field-grid">
                            <div>
                                <label className="field-label" htmlFor="puente-version">Versión</label>
                                <input className="text-input" id="puente-version" required pattern="[0-9]+\.[0-9]+\.[0-9]+" value={form.version} onChange={(e) => setForm({ ...form, version: e.target.value.trim() })} />
                                <small className="field-help">La misma de <code>motor/__init__.py</code>, por ejemplo 1.0.0.</small>
                            </div>
                            <div>
                                <label className="field-label" htmlFor="puente-tipo">Tipo</label>
                                <select className="text-input" id="puente-tipo" value={form.tipo} onChange={(e) => setForm({ ...form, tipo: e.target.value })}>
                                    {data.tipos.map((t) => <option key={t.clave} value={t.clave}>{t.nombre}</option>)}
                                </select>
                            </div>
                        </div>
                        <label className="field-label" htmlFor="puente-notas">Novedades de esta versión (opcional)</label>
                        <textarea className="text-input text-area" id="puente-notas" maxLength="2000" value={form.notas} onChange={(e) => setForm({ ...form, notas: e.target.value })} placeholder="Qué cambia para quien la usa" />
                        {progreso !== null && <div className="upload-progress" role="progressbar" aria-valuenow={progreso} aria-valuemin="0" aria-valuemax="100"><span style={{ width: `${progreso}%` }} /><small>{progreso < 100 ? `Subiendo… ${progreso}%` : 'Comprobando el archivo en el servidor…'}</small></div>}
                        <div className="modal-actions"><button className="primary-button" type="submit" disabled={!form.archivo || progreso !== null}><UploadCloud size={18} /> {progreso !== null ? 'Subiendo…' : 'Publicar versión'}</button></div>
                    </form>

                    <div className="table-wrap">
                        <table className="data-table">
                            <thead><tr><th>Versión</th><th>Tipo</th><th>Estado</th><th>Tamaño</th><th>Descargas</th><th>Publicó</th><th /></tr></thead>
                            <tbody>
                                {data.versiones.map((v) => (
                                    <tr key={v.id}>
                                        <td><strong>{v.version}</strong><small className="puente-fecha">{fecha(v.publicada_en)}</small></td>
                                        <td>{v.tipo_nombre}</td>
                                        <td>{v.vigente ? <span className="status-pill status-pill--green">Vigente</span> : <span className="status-pill status-pill--danger" title={v.motivo_retiro}>Retirada</span>}</td>
                                        <td>{tamanoLegible(v.tamano)}</td>
                                        <td>{v.descargas}</td>
                                        <td>{v.subido_por || '—'}</td>
                                        <td className="puente-acciones">
                                            <a className="secondary-button" href={v.url_descarga}><Download size={15} /> Descargar</a>
                                            {v.vigente && <button className="secondary-button" type="button" onClick={() => { setRetiro(v); setMotivo(''); setFeedback(null); }}><XCircle size={15} /> Retirar</button>}
                                        </td>
                                    </tr>
                                ))}
                                {data.versiones.length === 0 && <tr><td colSpan="7"><div className="table-empty">Sin versiones publicadas.</div></td></tr>}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}

            <Modal open={Boolean(retiro)} onClose={() => setRetiro(null)} title={`Retirar la versión ${retiro?.version || ''}`} subtitle="Deja de aparecer para descargar. El archivo y el registro se conservan en el historial.">
                <form className="modal-form" onSubmit={retirar}>
                    <FeedbackAlert feedback={feedback?.type === 'error' ? feedback : null} />
                    <label className="field-label" htmlFor="puente-motivo">Motivo</label>
                    <textarea className="text-input text-area" id="puente-motivo" required minLength="10" maxLength="500" value={motivo} onChange={(e) => setMotivo(e.target.value)} placeholder="Por qué se retira (por ejemplo, un error al abrir en Windows 10)" />
                    <div className="modal-actions"><button className="secondary-button" type="button" onClick={() => setRetiro(null)}>Cancelar</button><button className="primary-button" type="submit"><XCircle size={18} /> Retirar versión</button></div>
                </form>
            </Modal>
        </div>
    );
}
