import { ArrowLeft } from 'lucide-react';
import { Link, useParams } from 'react-router-dom';
import EmotionAverageResults from '../components/EmotionAverageResults';

export default function EmotionDetailPage() {
    const { id } = useParams();
    return <div className="page-stack">
        <Link className="back-link" to="/emociones"><ArrowLeft size={17} /> Volver a Emociones</Link>
        <section className="page-heading"><div><span className="eyebrow">Resultado del archivo</span><h1>Webinar {id}</h1><p>Nivel de atención y emoción ganadora del promedio cargado, sin recalcular valores.</p></div></section>
        <EmotionAverageResults key={id} meeting={id} />
    </div>;
}
