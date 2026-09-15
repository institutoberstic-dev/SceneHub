import { pageWindow } from './paginationWindow.js';

export default function Pagination({ total, page, pageSize = 15, onPageChange, onPageSizeChange, disabled = false }) {
    const pages = Math.max(1, Math.ceil(total / pageSize));
    const current = Math.max(1, Math.min(page, pages));
    return <nav className="smooth-pagination" aria-label="Paginación de resultados">
        <span aria-live="polite">{total ? (current - 1) * pageSize + 1 : 0}–{Math.min(current * pageSize, total)} de {total}</span>
        <div className="smooth-pagination__pages">
            <button type="button" disabled={disabled || current <= 1} onClick={() => onPageChange(current - 1)} aria-label="Página anterior">«</button>
            {pageWindow(current, pages).map(item => typeof item === 'number' ? <button type="button" key={item} disabled={disabled} aria-current={item === current ? 'page' : undefined} aria-label={`Página ${item}`} onClick={() => onPageChange(item)}>{item}</button> : <span key={item} aria-hidden="true">…</span>)}
            <button type="button" disabled={disabled || current >= pages} onClick={() => onPageChange(current + 1)} aria-label="Página siguiente">»</button>
        </div>
    </nav>;
}
