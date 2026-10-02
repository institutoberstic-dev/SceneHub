import { Check, Cpu } from 'lucide-react';

const OTHER_GROUP = { codigo: '__otras', nombre: 'Otras tecnologías' };

/** Agrupa por categoría conservando el orden del catálogo (el servidor ya lo entrega ordenado). */
export function groupByCategory(items) {
    const groups = new Map();
    items.forEach((item) => {
        const category = item.categoria || OTHER_GROUP;
        if (!groups.has(category.codigo)) groups.set(category.codigo, { ...category, items: [] });
        groups.get(category.codigo).items.push(item);
    });
    return [...groups.values()];
}

/**
 * Selección de tecnologías participantes desde el catálogo (sin texto libre).
 * `value` es la lista de códigos seleccionados. `current` son las tecnologías que
 * el escenario ya tiene: si alguna fue desactivada en el catálogo se muestra igual
 * para que no se pierda al guardar.
 */
export default function TechnologySelector({ catalog = [], current = [], value = [], onChange, loading = false, error = '', disabled = false, idPrefix = 'tech' }) {
    const known = new Set(catalog.map(item => item.codigo));
    const options = [
        ...catalog,
        ...current.filter(item => !known.has(item.codigo)).map(item => ({ ...item, inactiva: true })),
    ];
    const selected = new Set(value);
    const toggle = (codigo) => onChange(selected.has(codigo) ? value.filter(item => item !== codigo) : [...value, codigo]);

    return (
        <fieldset className="technology-selector" disabled={disabled}>
            <legend className="field-label">Tecnologías participantes</legend>
            <p className="technology-selector__help">Marca las tecnologías que intervienen en la simulación. Unity usa sus códigos para habilitar los elementos 3D del escenario.</p>
            {loading && <p className="technology-selector__state">Cargando catálogo de tecnologías…</p>}
            {!loading && error && <p className="technology-selector__state technology-selector__state--error" role="alert">{error}</p>}
            {!loading && !error && options.length === 0 && <p className="technology-selector__state">El catálogo de tecnologías está vacío. Un administrador debe cargarlo antes de asociarlas.</p>}
            {options.length > 0 && groupByCategory(options).map((group) => {
                const count = group.items.filter(item => selected.has(item.codigo)).length;
                return <div className="technology-group" key={group.codigo} role="group" aria-labelledby={`${idPrefix}-group-${group.codigo}`}>
                    <div className="technology-group__heading"><span id={`${idPrefix}-group-${group.codigo}`}>{group.nombre}</span>{count > 0 && <small>{count} de {group.items.length}</small>}</div>
                    <div className="technology-grid">
                        {group.items.map((item) => {
                            const checked = selected.has(item.codigo);
                            const id = `${idPrefix}-${item.codigo}`;
                            return <label key={item.codigo} htmlFor={id} className={`technology-option${checked ? ' is-checked' : ''}${item.inactiva ? ' is-inactive' : ''}`}>
                                <input id={id} type="checkbox" checked={checked} onChange={() => toggle(item.codigo)} />
                                <span className="technology-option__box" aria-hidden="true"><Check size={13} strokeWidth={3} /></span>
                                <span className="technology-option__text"><strong>{item.nombre}</strong><small>{item.codigo}{item.inactiva ? ' · desactivada en el catálogo' : ''}</small></span>
                            </label>;
                        })}
                    </div>
                </div>;
            })}
            {options.length > 0 && <small className="field-help">{value.length === 0 ? 'Sin tecnologías seleccionadas' : `${value.length} ${value.length === 1 ? 'tecnología seleccionada' : 'tecnologías seleccionadas'}`}</small>}
        </fieldset>
    );
}

/** Chips de solo lectura para tarjetas y detalle del escenario. */
export function TechnologyChips({ items = [], limit = 0, empty = null }) {
    if (!items.length) return empty;
    const shown = limit > 0 ? items.slice(0, limit) : items;
    const hidden = items.length - shown.length;
    return (
        <ul className="technology-chips" aria-label="Tecnologías participantes">
            {shown.map(item => <li key={item.codigo} title={item.categoria ? `${item.categoria.nombre} · ${item.codigo}` : item.codigo}><Cpu size={12} /> {item.nombre}</li>)}
            {hidden > 0 && <li className="technology-chips__more" title={items.slice(limit).map(item => item.nombre).join(', ')}>+{hidden}</li>}
        </ul>
    );
}
