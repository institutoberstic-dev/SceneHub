import { CalendarDays, Clock3, FileSpreadsheet, Info, LoaderCircle, MessageCircle, MoveRight, UploadCloud, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api, errorMessage } from '../http';
import Pagination from '../components/Pagination.jsx';

function meetingDate(value) {
    if (!value || !Number.isFinite(new Date(value).getTime())) return 'Fecha no disponible';
    return new Intl.DateTimeFormat('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value));
}

function meetingTime(value) {
    if (!value || !Number.isFinite(new Date(value).getTime())) return 'Hora no disponible';
    return new Intl.DateTimeFormat('es-CO', { hour: '2-digit', minute: '2-digit', hour12: true }).format(new Date(value));
}

export default function EmotionsPage() {
    const navigate = useNavigate();
    let currentUser = null;
    try { currentUser = JSON.parse(sessionStorage.getItem('scenehub_user') || 'null'); } catch { /* Laravel volverá a validar la sesión. */ }
    const canUpload = currentUser?.roles?.includes('admin') || currentUser?.permissions?.includes('emociones.cargar');
    const [meetings, setMeetings] = useState([]);
    const [loading, setLoading] = useState(true);
    const [failure, setFailure] = useState('');
    const [file, setFile] = useState(null);
    const [uploading, setUploading] = useState(false);
    const [message, setMessage] = useState('');
    const [page, setPage] = useState(1);
    const [pageSize, setPageSize] = useState(15);
    const [reload, setReload] = useState(0);
    const fileInput = useRef(null);

    useEffect(() => {
        api.get('/emociones-data')
            .then(({ data }) => setMeetings(Array.isArray(data?.meetings) ? data.meetings : []))
            .catch((error) => setFailure(errorMessage(error, 'No fue posible consultar las emociones.')))
            .finally(() => setLoading(false));
    }, [reload]);

    const upload = async (event) => {
        event.preventDefault();
        if (!file) return;
        setUploading(true);
        setFailure('');
        setMessage('');
        const body = new FormData();
        body.append('archivo', file);
        try {
            const { data } = await api.post('/emociones-data', body, { headers: { 'Content-Type': 'multipart/form-data' } });
            setMessage(data.message || 'Datos emocionales cargados correctamente.');
            setFile(null);
            event.target.reset();
            setPage(1);
            setReload(value => value + 1);
        } catch (error) {
            setFailure(errorMessage(error, 'No fue posible cargar los resultados emocionales.'));
        } finally {
            setUploading(false);
        }
    };

    return (
        <div className="page-stack">
            <section className="page-heading">
                <div>
                    <span className="eyebrow">Módulo</span>
                    <h1>Emociones</h1>
                    <p>Consulta las sesiones disponibles y accede a su espacio de contenido.</p>
                </div>
            </section>

            {failure && <div className="alert alert--error" role="alert">{failure}</div>}
            {message && <div className="alert alert--success" role="status">{message}</div>}

            {canUpload && <section className="content-card emotion-upload-card" aria-labelledby="emotion-upload-title">
                <div className="emotion-upload-card__intro">
                    <span className="eyebrow">Carga de datos</span>
                    <h2 id="emotion-upload-title">Resultados emocionales</h2>
                    <p>Sube el archivo completo o el promedio para actualizar el contenido de tus webinars.</p>
                    <p className="emotion-upload-card__hint" id="emotion-upload-help"><Info size={16} aria-hidden="true" /><span>Incluye la columna <code>id_meeting</code> para asociar los datos a cada webinar automáticamente.</span></p>
                </div>
                <form className="emotion-upload-form" onSubmit={upload} aria-busy={uploading}>
                    <label className={`file-picker${file ? ' file-picker--selected' : ''}${uploading ? ' file-picker--disabled' : ''}`}>
                        <span className="file-picker__icon">{file ? <FileSpreadsheet size={24} /> : <UploadCloud size={24} />}</span>
                        <span className="file-picker__copy"><strong>{file ? file.name : 'Selecciona tu archivo'}</strong><span>{file ? `${new Intl.NumberFormat('es-CO', { maximumFractionDigits: 1 }).format(file.size / 1024)} KB · Haz clic para cambiarlo` : 'Excel (.xlsx o .xls) · Completo o promedio'}</span></span>
                        <input ref={fileInput} type="file" accept=".xlsx,.xls" required disabled={uploading} aria-label="Archivo de resultados emocionales" aria-describedby="emotion-upload-help" onChange={event => { setFile(event.target.files[0] || null); setMessage(''); }} />
                    </label>
                    <div className="emotion-upload-form__actions">
                        <span className="emotion-upload-form__status" role="status">{uploading ? 'Procesando tu archivo…' : file ? 'Archivo listo para cargar' : 'Selecciona un archivo para continuar'}</span>
                        {file && <button className="icon-button" type="button" disabled={uploading} aria-label="Quitar archivo seleccionado" onClick={() => { setFile(null); fileInput.current.value = ''; }}><X size={18} /></button>}
                        <button className="primary-button" type="submit" disabled={uploading || !file}>{uploading ? <LoaderCircle className="upload-spinner" size={17} /> : <UploadCloud size={17} />}{uploading ? 'Cargando…' : 'Cargar resultados'}</button>
                    </div>
                </form>
            </section>}

            <section className="emotion-grid" aria-label="Sesiones del módulo Emociones">
                {loading && <div className="content-card loading-card emotion-grid__message">Cargando sesiones…</div>}
                {!loading && !failure && meetings.length === 0 && <div className="content-card emotions-empty-view emotion-grid__message">No se encontraron sesiones.</div>}
                {meetings.slice((page - 1) * pageSize, page * pageSize).map((meeting) => (
                    <article
                        className="emotion-card"
                        key={meeting.id}
                        role="link"
                        tabIndex={0}
                        onClick={() => navigate(`/emociones/${meeting.id}`)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter' || event.key === ' ') {
                                event.preventDefault();
                                navigate(`/emociones/${meeting.id}`);
                            }
                        }}
                        aria-label={`Abrir ${meeting.title}`}
                    >
                        <span className="emotion-card__icon"><MessageCircle size={24} /></span>
                        <h2>{meeting.title}</h2>
                        <p>{meeting.topic}</p>
                        {meeting.start_datetime && <div className="emotion-card__meta">
                            <span><CalendarDays size={15} /> {meetingDate(meeting.start_datetime)}</span>
                            <span><Clock3 size={15} /> {meetingTime(meeting.start_datetime)}</span>
                        </div>}
                        <footer>Ver contenido <MoveRight size={16} /></footer>
                    </article>
                ))}
            </section>
            {!loading && !failure && <Pagination total={meetings.length} page={page} pageSize={pageSize} onPageChange={setPage} onPageSizeChange={size => { setPageSize(size); setPage(1); }} />}
        </div>
    );
}
