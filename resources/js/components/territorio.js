// Etiquetas y utilidades compartidas por las pantallas del sistema territorial.

export const estados = {
    borrador: { label: 'Borrador', tone: '' },
    validado: { label: 'Validado', tone: 'status-pill--blue' },
    publicado: { label: 'Publicado', tone: 'status-pill--green' },
    retirado: { label: 'Retirado', tone: 'status-pill--danger' },
};

export const resultados = {
    ok: { label: 'Correcto', tone: 'status-pill--green' },
    advertencia: { label: 'Advertencia', tone: 'status-pill--warning' },
    error: { label: 'Error', tone: 'status-pill--danger' },
};

const orden = { error: 3, advertencia: 2, ok: 1 };

/** Peor resultado de un conjunto de validaciones (una advertencia confirmada cuenta como correcta). */
export function peorResultado(validaciones) {
    return validaciones.reduce((peor, v) => {
        const resultado = v.resultado === 'advertencia' && v.confirmada_en ? 'ok' : v.resultado;
        return (orden[resultado] || 0) > (orden[peor] || 0) ? resultado : peor;
    }, 'ok');
}

export function tamanoLegible(bytes) {
    if (!Number.isFinite(bytes)) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 ** 2) return `${(bytes / 1024).toFixed(1)} KB`;
    if (bytes < 1024 ** 3) return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
    return `${(bytes / 1024 ** 3).toFixed(2)} GB`;
}

export function fecha(valor) {
    return valor ? new Date(valor).toLocaleString('es-CO') : '—';
}

/** Propone el tipo de archivo a partir de su nombre (el usuario puede cambiarlo). */
export function tipoSugerido(nombre = '') {
    const n = nombre.toLowerCase();
    if (n.endsWith('.glb')) return 'escena_glb';
    if (n.includes('georef') && n.endsWith('.json')) return 'georef_json';
    if (n.includes('sitio') && n.endsWith('.json')) return 'sitio_json';
    if (n.includes('malla') && n.endsWith('.gpkg')) return 'malla_gpkg';
    for (const capa of ['huellas', 'cercas', 'copas', 'superficies', 'corredores', 'infraestructura']) {
        if (n.includes(capa)) return capa;
    }
    if (n.includes('orto') || /\.(tif|tiff)$/.test(n)) return 'ortofoto';
    return '';
}

export const accionesRegistro = {
    creado: 'Borrador creado',
    archivo_subido: 'Archivo subido',
    archivo_eliminado: 'Archivo eliminado',
    validado: 'Validación',
    advertencia_confirmada: 'Advertencia confirmada',
    publicado: 'Publicado',
    retirado: 'Retirado',
};
