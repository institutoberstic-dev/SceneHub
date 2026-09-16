import SolarResults from '../components/SolarResults';

export default function ResultsPage() {
    return (
        <div className="page-stack">
            <section className="page-heading">
                <div>
                    <span className="eyebrow">Resultados por escenario</span>
                    <h1>Exposición de resultados</h1>
                    <p>Selecciona el escenario y el documento importado cuyos datos deseas consultar.</p>
                </div>
            </section>
            <SolarResults />
        </div>
    );
}
