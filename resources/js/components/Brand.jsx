export default function Brand({ compact = false }) {
    return (
        <div className={`brand ${compact ? 'brand--compact' : ''}`}>
            <span className="brand-mark" aria-hidden="true">
                <i className="brand-mark__face brand-mark__face--one" />
                <i className="brand-mark__face brand-mark__face--two" />
                <i className="brand-mark__face brand-mark__face--three" />
            </span>
            <span>
                <strong>SceneHub</strong>
                {!compact && <small>Portal de escenarios</small>}
            </span>
        </div>
    );
}
