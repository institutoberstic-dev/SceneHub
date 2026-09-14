import { BarChart3, Droplets, Sun } from 'lucide-react';
import { useState } from 'react';
import SolarResults from '../components/SolarResults';

export default function ResultsPage() {
    const [tab, setTab] = useState('solar');
    const title = tab === 'solar' ? 'Simulación solar' : 'Electrolizador';

    return (
        <div className="page-stack">
            <section className="page-heading">
                <div>
                    <span className="eyebrow">Resultados por escenario</span>
                    <h1>Resultados de simulación</h1>
                    <p>Consulta los muestreos y cambios de las simulaciones de cada escenario.</p>
                </div>
            </section>

            <div className="tabs">
                <button className={tab === 'solar' ? 'active' : ''} type="button" onClick={() => setTab('solar')}>
                    <Sun size={17} /> Simulación solar
                </button>
                <button className={tab === 'hydrogen' ? 'active' : ''} type="button" onClick={() => setTab('hydrogen')}>
                    <Droplets size={17} /> Electrolizador
                </button>
            </div>

            {tab === 'solar' ? <SolarResults /> : <section className="content-card result-placeholder">
                <div className="section-heading section-heading--inside">
                    <div>
                        <h2>{title}</h2>
                        <p>Contenedor reservado para el esquema de resultados que se definirá más adelante.</p>
                    </div>
                </div>
                <div className="result-placeholder__canvas" aria-label={`Área pendiente para ${title}`}>
                    <BarChart3 size={28} />
                    <span>Área de visualización pendiente</span>
                </div>
            </section>}
        </div>
    );
}
