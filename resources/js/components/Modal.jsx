import { X } from 'lucide-react';

export default function Modal({ open, title, subtitle, children, onClose, wide = false }) {
    if (!open) return null;

    return (
        <div className="modal-backdrop" role="presentation" onMouseDown={onClose}>
            <section className={`modal-card ${wide ? 'modal-card--wide' : ''}`} role="dialog" aria-modal="true" aria-label={title} onMouseDown={(event) => event.stopPropagation()}>
                <header className="modal-card__header">
                    <div>
                        <h2>{title}</h2>
                        {subtitle && <p>{subtitle}</p>}
                    </div>
                    <button className="icon-button" type="button" onClick={onClose} aria-label="Cerrar">
                        <X size={19} />
                    </button>
                </header>
                {children}
            </section>
        </div>
    );
}
