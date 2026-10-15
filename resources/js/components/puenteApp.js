// Utilidades de la pantalla «Puente de datos» (descarga de la app de escritorio).

const VERSION = /^\d+\.\d+\.\d+$/;

export function versionValida(texto = '') {
    return VERSION.test(texto.trim());
}

/** Propone la siguiente versión de parche a partir de la última publicada (1.2.3 → 1.2.4). */
export function siguienteVersion(versiones = []) {
    const validas = versiones.map((v) => v.version).filter(versionValida);
    if (!validas.length) return '1.0.0';
    const mayor = validas
        .map((v) => v.split('.').map(Number))
        .sort((a, b) => a[0] - b[0] || a[1] - b[1] || a[2] - b[2])
        .at(-1);
    return `${mayor[0]}.${mayor[1]}.${mayor[2] + 1}`;
}

/** Tipo sugerido según la extensión del archivo elegido. */
export function tipoPorArchivo(nombre = '') {
    const extension = nombre.toLowerCase().split('.').pop();
    if (extension === 'zip') return 'portable';
    if (extension === 'exe' || extension === 'msi') return 'instalador';
    return '';
}

/** Comando de PowerShell para comprobar que el archivo descargado llegó completo. */
export function comandoVerificacion(nombreArchivo) {
    return `(Get-FileHash "$env:USERPROFILE\\Downloads\\${nombreArchivo}" -Algorithm SHA256).Hash`;
}
