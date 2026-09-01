import { Component } from 'react';

export default class AppErrorBoundary extends Component {
    constructor(props) {
        super(props);
        this.state = { error: null };
    }

    static getDerivedStateFromError(error) {
        return { error };
    }

    componentDidCatch(error, info) {
        console.error('SceneHub no pudo renderizar la aplicación.', error, info);
    }

    render() {
        if (!this.state.error) return this.props.children;

        return (
            <main className="startup-error" role="alert">
                <section>
                    <span className="eyebrow">Error de interfaz</span>
                    <h1>No fue posible iniciar SceneHub</h1>
                    <p>Recarga la página. Si el problema continúa, revisa la consola del navegador y los procesos de Laravel y Vite.</p>
                    {import.meta.env.DEV && <pre>{this.state.error.message}</pre>}
                    <button className="primary-button" type="button" onClick={() => window.location.reload()}>
                        Recargar aplicación
                    </button>
                </section>
            </main>
        );
    }
}
