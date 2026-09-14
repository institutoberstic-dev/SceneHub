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
    const [page, setPage] = useState(1);
    const [pageSize, setPageSize] = useState(15);

    useEffect(() => {
        api.get('/api/emociones')
            .then(({ data }) => setMeetings(Array.isArray(data?.meetings) ? data.meetings : []))
            .catch((error) => setFailure(errorMessage(error, 'No fue posible consultar las emociones.')))
            .finally(() => setLoading(false));
    }, []);

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
