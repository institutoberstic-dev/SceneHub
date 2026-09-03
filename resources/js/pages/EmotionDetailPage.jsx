import { ArrowLeft } from 'lucide-react';
import { Link } from 'react-router-dom';

export default function EmotionDetailPage() {
    return (
        <div className="page-stack">
            <Link className="back-link" to="/emociones"><ArrowLeft size={17} /> Volver a Emociones</Link>
            <section className="page-heading">
                <div>
                    <span className="eyebrow">Emociones</span>
                    <h1>Contenido de la sesión</h1>
                    <p>Este espacio está preparado para incorporar nuevos datos próximamente.</p>
                </div>
            </section>
            <section className="content-card emotions-empty-view" aria-label="Contenido futuro de la sesión" />
        </div>
    );
}
