import { AlertTriangle, ArrowRight, Check, FileSpreadsheet, FileText, Loader2, Sparkles, UploadCloud, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { csrfRequest, errorMessage } from '../http';
import { ACTION_LABELS, KIND, fileKey, planScenarioFiles, suggestScenarioNumber } from './scenarioFilePlan.js';

const MAX_FILES = 10;
const ACCEPTED = ['xlsx', 'xls', 'doc', 'docx', 'pdf'];
const extensionOf = (name) => (name.split('.').pop() || '').toLowerCase();
const formatSize = (bytes) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);
const KIND_LABELS = { [KIND.results]: 'Archivo de escenario', [KIND.report]: 'Informe', [KIND.document]: 'Documento' };
const ORIGIN_LABELS = { nombre: 'número tomado del nombre', contenido: 'mismo contenido que este informe', nombre_original: 'mismo nombre que un informe anterior', consecutivo: 'siguiente consecutivo' };

function resultsSummary(info) {
    if (!info) return '';
    const sheets = Object.entries(info.registros || {}).map(([sheet, count]) => `${sheet} ${count}`).join(' · ');
    return `Formato ${info.formato === 'extendido' ? 'extendido' : 'básico'} · ${info.columnas?.length || 0} variables · ${sheets}`;
}

/**
 * Carga de archivos con arrastrar y soltar. Cada archivo se analiza en el servidor al soltarlo
 * (sin guardarlo): se reconoce el libro de resultados («archivo de escenario»), los informes y
 * los documentos, y se muestra con qué nombre quedará. Las casillas deciden qué se carga.
 *
 * onChange recibe { files, ready, scenarioFile, suggestion, analyzing }.
 */
export default function ScenarioFileDropzone({ id, scenarioId = null, scenarioNumber = null, disabled = false, onChange }) {
    const [entries, setEntries] = useState([]);
    const [rows, setRows] = useState({});
    const [context, setContext] = useState({ existentes: [], numeros_ocupados: [], numero: null });
    const [selected, setSelected] = useState(() => new Set());
    const [pending, setPending] = useState(0);
    const [error, setError] = useState('');
    const [dragging, setDragging] = useState(false);
    const generation = useRef(0);
    const onChangeRef = useRef(onChange);
    onChangeRef.current = onChange;
    const rowsRef = useRef(rows);
    rowsRef.current = rows;

    /** Analiza archivos (solo los nuevos, o todos al cambiar de escenario) y fusiona el resultado. */
    const analyze = useCallback(async (list, { replace = false } = {}) => {
        const ticket = replace ? ++generation.current : generation.current;
        if (!list.length) return;
        setPending((count) => count + 1);
        setError('');
        const body = new FormData();
        list.forEach(({ file }) => body.append('archivos[]', file));
        if (scenarioId) body.append('escenario_id', scenarioId);
        try {
            const { data } = await csrfRequest({ method: 'post', url: '/escenarios-analizar', data: body });
            if (ticket !== generation.current) return;
            setContext({ existentes: data.existentes || [], numeros_ocupados: data.escenario?.numeros_ocupados || [], numero: data.escenario?.numero ?? null });
            const analyzed = {};
            data.archivos.forEach((row) => { const entry = list[row.indice]; analyzed[entry.key] = { ...row, key: entry.key, fecha: Math.floor((entry.file.lastModified || 0) / 1000) || null }; });
            // Al reanalizar se reemplazan solo los archivos enviados; los que se agregaron mientras tanto se conservan.
            setRows((current) => ({ ...current, ...analyzed }));
            setSelected((current) => {
                const next = new Set(replace ? [...current].filter((key) => !analyzed[key]) : current);
                let resultsTaken = [...next].some((key) => (analyzed[key] || rowsRef.current[key])?.tipo === KIND.results);
                list.forEach(({ key }) => {
                    const row = analyzed[key];
                    if (!row?.valido) { next.delete(key); return; }
                    if (row.tipo === KIND.results) {
                        // El primer libro de resultados válido se propone como archivo del escenario.
                        if (!resultsTaken) { next.add(key); resultsTaken = true; }
                        return;
                    }
                    next.add(key);
                });
                return next;
            });
        } catch (requestError) {
            if (ticket !== generation.current) return;
            setError(errorMessage(requestError, 'No fue posible analizar los archivos.'));
            const failed = {};
            list.forEach(({ key, file }) => { failed[key] = { key, nombre_original: file.name, extension: extensionOf(file.name), tamano: file.size, valido: false, tipo: null, errores: ['No se pudo analizar; vuelve a intentarlo.'] }; });
            setRows((current) => ({ ...current, ...failed }));
        } finally {
            setPending((count) => Math.max(0, count - 1));
        }
    }, [scenarioId]); // eslint-disable-line react-hooks/exhaustive-deps

    // Al cambiar de escenario, los nombres dependen de lo que ese escenario ya tiene: se reanaliza todo.
    const entriesRef = useRef(entries);
    entriesRef.current = entries;
    useEffect(() => {
        if (entriesRef.current.length) analyze(entriesRef.current, { replace: true });
    }, [scenarioId]); // eslint-disable-line react-hooks/exhaustive-deps

    const addFiles = (fileList) => {
        if (disabled) return;
        const incoming = Array.from(fileList || []);
        if (!incoming.length) return;
        const known = new Set(entries.map((entry) => entry.key));
        const fresh = [];
        const rejected = {};
        incoming.forEach((file) => {
            const key = fileKey(file);
            if (known.has(key)) return;
            known.add(key);
            if (!ACCEPTED.includes(extensionOf(file.name))) {
                rejected[key] = { key, nombre_original: file.name, extension: extensionOf(file.name), tamano: file.size, valido: false, tipo: null, errores: ['Formato no admitido: solo Word (.doc, .docx), PDF o Excel (.xlsx, .xls).'] };
            }
            fresh.push({ key, file });
        });
        const room = MAX_FILES - entries.length;
        const accepted = fresh.slice(0, Math.max(0, room));
        if (fresh.length > accepted.length) setError(`Puedes cargar hasta ${MAX_FILES} archivos a la vez; se omitieron ${fresh.length - accepted.length}.`);
        if (!accepted.length) return;
        setEntries((current) => [...current, ...accepted]);
        setRows((current) => ({ ...current, ...Object.fromEntries(Object.entries(rejected).filter(([key]) => accepted.some((entry) => entry.key === key))) }));
        analyze(accepted.filter((entry) => !rejected[entry.key]));
    };

    const removeFile = (key) => {
        setEntries((current) => current.filter((entry) => entry.key !== key));
        setRows((current) => { const next = { ...current }; delete next[key]; return next; });
        setSelected((current) => { const next = new Set(current); next.delete(key); return next; });
    };

    const toggle = (key) => {
        setSelected((current) => {
            const next = new Set(current);
            if (next.has(key)) { next.delete(key); return next; }
            // Solo un archivo de datos por escenario: marcar uno desmarca el otro.
            if (rows[key]?.tipo === KIND.results) [...next].forEach((other) => { if (rows[other]?.tipo === KIND.results) next.delete(other); });
            next.add(key);
            return next;
        });
    };

    const orderedRows = entries.map((entry) => rows[entry.key] || { key: entry.key, nombre_original: entry.file.name, extension: extensionOf(entry.file.name), tamano: entry.file.size, pendiente: true });
    const scenarioRow = orderedRows.find((row) => row.valido && row.tipo === KIND.results && selected.has(row.key)) || null;
    const suggestedNumber = scenarioId ? context.numero : suggestScenarioNumber(scenarioRow?.numero_escenario_en_nombre ?? null, context.numeros_ocupados);
    const suggestion = { numero: suggestedNumber, nombre: suggestedNumber ? `Escenario ${suggestedNumber}` : '', ocupados: context.numeros_ocupados };
    const effectiveNumber = scenarioId ? context.numero : (Number(scenarioNumber) || null);
    const plan = planScenarioFiles({ rows: orderedRows, selected, scenarioNumber: effectiveNumber, existing: context.existentes });

    const chosen = orderedRows.filter((row) => row.valido && selected.has(row.key));
    const hasConflicts = chosen.some((row) => plan.get(row.key)?.conflictos.length);
    const ready = pending === 0 && chosen.length > 0 && !hasConflicts;

    useEffect(() => {
        onChangeRef.current?.({
            files: chosen.map((row) => entries.find((entry) => entry.key === row.key)?.file).filter(Boolean),
            ready,
            analyzing: pending > 0,
            scenarioFile: scenarioRow,
            suggestion,
            conflicts: hasConflicts,
        });
    }, [chosen.map((row) => row.key).join(','), ready, pending, scenarioRow?.key, suggestion.numero, suggestion.ocupados.join(',')]); // eslint-disable-line react-hooks/exhaustive-deps

    const onDrop = (event) => {
        event.preventDefault();
        setDragging(false);
        addFiles(event.dataTransfer?.files);
    };

    return (
        <div className={`file-drop ${disabled ? 'is-disabled' : ''}`}>
            <label
                className={`upload-zone file-drop__zone ${dragging ? 'is-dragging' : ''}`}
                htmlFor={id}
                onDragEnter={(event) => { event.preventDefault(); if (!disabled) setDragging(true); }}
                onDragOver={(event) => { event.preventDefault(); if (event.dataTransfer) event.dataTransfer.dropEffect = disabled ? 'none' : 'copy'; }}
                onDragLeave={(event) => { if (!event.currentTarget.contains(event.relatedTarget)) setDragging(false); }}
                onDrop={onDrop}
            >
                <UploadCloud size={30} />
                <strong>{disabled ? 'Selecciona primero el escenario' : 'Arrastra aquí los archivos o haz clic para buscarlos'}</strong>
                <span>Word, PDF o Excel · hasta {MAX_FILES} archivos · máximo 50 MB por archivo. El sistema reconoce el libro de resultados y los informes (por su nombre) y les asigna su nombre final.</span>
                <input id={id} type="file" multiple disabled={disabled} accept=".xlsx,.xls,.doc,.docx,.pdf" onChange={(event) => { addFiles(event.target.files); event.target.value = ''; }} />
            </label>

            {error && <p className="file-drop__error" role="alert"><AlertTriangle size={15} /> {error}</p>}

            {orderedRows.length > 0 && (
                <ul className="file-drop__list" aria-live="polite">
                    {orderedRows.map((row) => {
                        const item = plan.get(row.key);
                        const checked = selected.has(row.key) && Boolean(row.valido);
                        const isScenarioFile = row.tipo === KIND.results && row.valido;
                        const issues = [...(row.errores || []), ...(checked ? item?.conflictos || [] : [])];
                        const Icon = ['xlsx', 'xls'].includes(row.extension) ? FileSpreadsheet : FileText;
                        return (
                            <li key={row.key} className={`file-row ${isScenarioFile ? 'file-row--scenario' : ''} ${checked ? 'is-checked' : ''} ${issues.length ? 'has-issues' : ''}`}>
                                <label className="file-row__check">
                                    <input type="checkbox" checked={checked} disabled={!row.valido || row.pendiente} onChange={() => toggle(row.key)} aria-label={isScenarioFile ? `Usar ${row.nombre_original} como archivo de datos del escenario` : `Cargar ${row.nombre_original}`} />
                                    <span className="file-row__box" aria-hidden="true"><Check size={13} /></span>
                                </label>
                                <div className="file-row__body">
                                    <div className="file-row__title">
                                        <Icon size={17} />
                                        <strong title={row.nombre_original}>{row.nombre_original}</strong>
                                        {row.pendiente && <span className="file-badge"><Loader2 size={12} className="upload-spinner" /> Analizando…</span>}
                                        {!row.pendiente && row.valido && <span className={`file-badge file-badge--${row.tipo}`}>{isScenarioFile && <Sparkles size={12} />}{KIND_LABELS[row.tipo]}{row.tipo === KIND.report && item?.numero ? ` ${item.numero}` : ''}</span>}
                                        {!row.pendiente && !row.valido && <span className="file-badge file-badge--error">{row.tipo === KIND.results ? 'Resultados con errores' : 'No se puede cargar'}</span>}
                                        <small>{formatSize(row.tamano || 0)}</small>
                                    </div>
                                    {isScenarioFile && <p className="file-row__hint">{checked ? 'Sí, se cargará como el archivo de datos del escenario.' : 'Reconocido como archivo de datos del escenario. Márcalo para cargarlo.'} {resultsSummary(row.resultados)}</p>}
                                    {checked && item?.destino && (
                                        <p className="file-row__target">
                                            <ArrowRight size={14} /> Se guardará como <b>{item.destino}</b>
                                            {item.accion && <span className={`file-action file-action--${item.accion}`}>{ACTION_LABELS[item.accion]}</span>}
                                            {row.tipo === KIND.report && item.origen && <small>({ORIGIN_LABELS[item.origen]})</small>}
                                        </p>
                                    )}
                                    {checked && !item?.destino && row.tipo === KIND.results && <p className="file-row__target"><ArrowRight size={14} /> Se guardará como <b>resultados escenario N.{row.extension}</b> cuando definas el número del escenario.</p>}
                                    {checked && item?.advertencias.map((warning) => <p className="file-row__warning" key={warning}><AlertTriangle size={14} /> {warning}</p>)}
                                    {issues.length > 0 && <ul className="file-row__errors">{issues.slice(0, 6).map((issue) => <li key={issue}>{issue}</li>)}{issues.length > 6 && <li>y {issues.length - 6} más…</li>}</ul>}
                                </div>
                                <button className="icon-button file-row__remove" type="button" onClick={() => removeFile(row.key)} aria-label={`Quitar ${row.nombre_original}`} title="Quitar de la carga"><X size={16} /></button>
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}
