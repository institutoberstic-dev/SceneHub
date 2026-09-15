import { CalendarDays, Clock3, MessageCircle, MoveRight } from 'lucide-react';
import { useEffect, useState } from 'react';
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
    const [meetings, setMeetings] = useState([]);
    const [loading, setLoading] = useState(true);
    const [failure, setFailure] = useState('');
    const [file, setFile] = useState(null);
    const [uploading, setUploading] = useState(false);
    const [message, setMessage] = useState('');
    const [page, setPage] = useState(1);
    const [pageSize, setPageSize] = useState(15);
    const [reload, setReload] = useState(0);

    useEffect(() => {
        api.get('/api/emociones')
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
            const { data } = await api.post('/api/emociones/datos', body, { headers: { 'Content-Type': 'multipart/form-data' } });
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

            {failure && <div className="alert alert--error">{failure}</div>}
            {message && <div className="alert alert--success">{message}</div>}

            <section className="content-card emotion-upload-card">
                <div className="page-heading page-heading--compact">
                    <div><span className="eyebrow">Carga de datos</span><h2>Resultados emocionales</h2><p>Carga el archivo completo o el promedio. Sus identificadores enlazan automáticamente los datos con cada webinar.</p></div>
                </div>
                <form className="solar-filters solar-filters--upload" onSubmit={upload}>
                    <label>Archivo de resultados<input className="text-input" type="file" accept=".xlsx,.xls" required onChange={event => setFile(event.target.files[0] || null)} /></label>
                    <button className="primary-button" type="submit" disabled={uploading || !file}>{uploading ? 'Cargando…' : 'Cargar resultados'}</button>
                </form>
                <p className="field-help">El archivo debe incluir el identificador del webinar en la columna <code>id_meeting</code>. La API asociará cada registro automáticamente.</p>
            </section>

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
