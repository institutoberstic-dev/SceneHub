// Vista previa de los nombres con que se guardarán los archivos de un escenario.
// Replica las reglas de app/Services/ScenarioFiles.php (el servidor vuelve a validar al guardar):
//  - libro de resultados ⇒ «resultados escenario {número del escenario}.xlsx» (uno por escenario);
//  - informe (el nombre dice «informe»/«reporte», cualquier formato) ⇒ «informe escenario {n}.{ext}»: el número del nombre, el del informe
//    con el mismo contenido o mismo nombre original, o el siguiente consecutivo;
//  - otros documentos conservan su nombre.

export const KIND = { results: 'resultados', report: 'informe', document: 'documento' };

export const resultsName = (number, extension) => `resultados escenario ${number}.${String(extension).toLowerCase()}`;
export const reportName = (number, extension) => `informe escenario ${number}.${String(extension).toLowerCase()}`;

export const ACTION_LABELS = {
    nuevo: 'Nuevo',
    reemplaza: 'Reemplaza la versión anterior',
    sin_cambios: 'Sin cambios: no se guarda de nuevo',
    omitido: 'No se carga: el guardado es igual o más reciente',
};

const formatDate = (seconds) => new Date(seconds * 1000).toLocaleString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });

/** Número sugerido para un escenario nuevo: el del nombre del libro si está libre; si no, el menor libre. */
export function suggestScenarioNumber(hint, occupied = []) {
    const used = new Set(occupied.map(Number));
    if (Number.isInteger(hint) && hint > 0 && !used.has(hint)) return hint;
    let number = 1;
    while (used.has(number)) number += 1;
    return number;
}

/**
 * @param {object} params
 * @param {Array<object>} params.rows filas del análisis del servidor, cada una con `key` propia
 * @param {Set<string>} params.selected claves de los archivos marcados
 * @param {number|null} params.scenarioNumber número del escenario (null si aún no se define)
 * @param {Array<object>} params.existing archivos que ya tiene el escenario (`existentes`)
 * @returns {Map<string, {destino: string|null, numero: number|null, origen: string|null, accion: string|null, conflictos: string[], advertencias: string[]}>}
 */
export function planScenarioFiles({ rows, selected, scenarioNumber = null, existing = [] }) {
    const chosen = rows.filter((row) => row.valido && selected.has(row.key));
    const byName = new Map(existing.map((file) => [file.nombre.toLowerCase(), file]));
    const used = existing.filter((file) => file.tipo === KIND.report).map((file) => file.numero);
    chosen.forEach((row) => { if (row.tipo === KIND.report && row.numero_informe) used.push(row.numero_informe); });

    const plan = new Map();
    chosen.forEach((row) => {
        const item = { destino: row.nombre_original, numero: null, origen: null, accion: null, conflictos: [], advertencias: [] };
        if (row.tipo === KIND.results) {
            item.numero = scenarioNumber || null;
            item.destino = item.numero ? resultsName(item.numero, row.extension) : null;
            const hinted = row.numero_escenario_en_nombre;
            if (hinted && item.numero && hinted !== item.numero) {
                item.advertencias.push(`El nombre indica el escenario ${hinted}; se guardará como «${item.destino}».`);
            }
        } else if (row.tipo === KIND.report) {
            item.numero = row.numero_informe || null;
            item.origen = row.origen_numero || null;
            if (!item.numero) {
                item.numero = Math.max(0, ...used) + 1;
                item.origen = 'consecutivo';
                used.push(item.numero);
            }
            item.destino = reportName(item.numero, row.extension);
        }
        plan.set(row.key, item);
    });

    const taken = new Map();
    chosen.forEach((row) => {
        const item = plan.get(row.key);
        if (!item.destino) return;
        // El mismo número cuenta como el mismo archivo aunque cambie la extensión (.xls/.xlsx, .doc/.docx).
        const key = row.tipo === KIND.document ? `documento:${item.destino.toLowerCase()}` : `${row.tipo}:${item.numero}`;
        if (taken.has(key)) {
            const other = taken.get(key);
            if (row.tipo === KIND.results) item.conflictos.push(`Ya hay otro libro de resultados marcado («${other.nombre_original}»). Cada escenario tiene un solo archivo de datos; deja solo uno.`);
            else if (row.tipo === KIND.report) item.conflictos.push(`Se guardaría como «${item.destino}», igual que «${other.nombre_original}». Cambia el número en el nombre de uno de los dos.`);
            else item.conflictos.push('El archivo está repetido en esta carga.');
            return;
        }
        taken.set(key, row);
    });

    // Un libro de resultados guardado con un nombre anterior en la versión vigente queda reemplazado.
    const latestVersion = Math.max(0, ...existing.map((file) => Number(file.version) || 0));
    const legacyResults = existing.some((file) => file.resultados_anterior && Number(file.version) === latestVersion);
    chosen.forEach((row) => {
        const item = plan.get(row.key);
        const current = item.destino ? byName.get(item.destino.toLowerCase()) : null;
        // El mismo número en otro formato (informe 1 .docx ⇒ .pdf) es el mismo archivo y se reemplaza.
        const sameNumber = item.destino && row.tipo !== KIND.document
            ? existing.find((file) => file.tipo === row.tipo && file.numero === item.numero && file.nombre.toLowerCase() !== item.destino.toLowerCase())
            : null;
        const previous = current || sameNumber;
        if (!item.destino) item.accion = null;
        else if (current && current.sha256 === row.sha256) item.accion = 'sin_cambios';
        else if (row.tipo === KIND.report && previous && row.fecha && previous.fecha_archivo && row.fecha <= previous.fecha_archivo) {
            // Un informe solo se actualiza con un archivo más reciente que el guardado.
            item.accion = 'omitido';
            item.advertencias.push(`No se cargará: el archivo es del ${formatDate(row.fecha)} y «${previous.nombre}» guardado es del ${formatDate(previous.fecha_archivo)}; solo se actualiza con uno más reciente.`);
        } else if (previous || (row.tipo === KIND.results && legacyResults)) item.accion = 'reemplaza';
        else item.accion = 'nuevo';
    });

    return plan;
}

/** Clave estable de un archivo elegido en el navegador. */
export const fileKey = (file) => `${file.name}|${file.size}|${file.lastModified}`;
