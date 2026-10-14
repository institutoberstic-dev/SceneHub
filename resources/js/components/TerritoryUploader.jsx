import { CheckCircle2, Circle, UploadCloud } from 'lucide-react';
import { useState } from 'react';
import { csrfRequest, errorList } from '../http';
import { tamanoLegible, tipoSugerido } from './territorio';

const vacio = () => ({ tipo: '', archivo: null, descripcion: '', capa: '', sha256_declarado: '', fuente: '', licencia: '', fecha_captura: '', es_sensible: false });

/**
 * Carga de un archivo del paquete territorial: arrastrar y soltar, tipo,
 * metadatos de procedencia y barra de progreso. El servidor valida al recibir.
 */
export default function TerritoryUploader({ paquete, onUploaded, onError }) {
    const [form, setForm] = useState(vacio);
    const [progreso, setProgreso] = useState(null);
    const [arrastrando, setArrastrando] = useState(false);
    const tipos = paquete.tipos || [];
    const tipo = tipos.find((t) => t.clave === form.tipo);
    const presentes = new Set(paquete.archivos.map((a) => a.tipo));
    const esGeo = tipo && (tipo.extensiones || []).includes('gpkg');

    const elegir = (archivo) => {
        if (!archivo) return;
        setForm((actual) => ({ ...actual, archivo, tipo: actual.tipo || tipoSugerido(archivo.name) }));
    };

    const subir = async (event) => {
        event.preventDefault();
        if (!form.archivo || !form.tipo) return;
        const cuerpo = new FormData();
        Object.entries(form).forEach(([clave, valor]) => {
            if (clave === 'es_sensible') cuerpo.append(clave, valor ? '1' : '0');
            else if (valor !== '' && valor !== null) cuerpo.append(clave, valor);
        });
        setProgreso(0);
        try {
            const { data } = await csrfRequest({
                method: 'post',
                url: `/territorio-paquetes/${paquete.id}/archivos`,
                data: cuerpo,
                onUploadProgress: (e) => e.total && setProgreso(Math.round((e.loaded * 100) / e.total)),
            });
            setForm(vacio());
            onUploaded(data);
        } catch (error) {
            onError(errorList(error, 'No fue posible subir el archivo.'));
        } finally { setProgreso(null); }
    };

    return (
        <form className="territory-uploader" onSubmit={subir}>
            <div className="territory-checklist" aria-label="Archivos obligatorios">
                {tipos.filter((t) => t.obligatorio).map((t) => (
                    <span key={t.clave} className={presentes.has(t.clave) ? 'is-done' : ''}>{presentes.has(t.clave) ? <CheckCircle2 size={15} /> : <Circle size={15} />} {t.nombre}</span>
                ))}
            </div>

            <label
                className={`upload-zone${arrastrando ? ' upload-zone--active' : ''}`}
                htmlFor="territorio-archivo"
                onDragOver={(e) => { e.preventDefault(); setArrastrando(true); }}
                onDragLeave={() => setArrastrando(false)}
                onDrop={(e) => { e.preventDefault(); setArrastrando(false); elegir(e.dataTransfer.files?.[0]); }}
            >
                <UploadCloud size={30} />
                <strong>{form.archivo ? `${form.archivo.name} · ${tamanoLegible(form.archivo.size)}` : 'Arrastre aquí un archivo del paquete o haga clic para elegirlo'}</strong>
                <span>{tipo ? `Se admite: .${tipo.extensiones.join(', .')} · máximo ${tipo.max_mb} MB` : 'GeoPackage, GeoJSON, JSON, GLB u ortofoto. Un archivo a la vez.'}</span>
                <input id="territorio-archivo" type="file" onChange={(e) => elegir(e.target.files?.[0])} />
            </label>

            <div className="field-grid">
                <div>
                    <label className="field-label" htmlFor="territorio-tipo">Tipo de archivo</label>
                    <select className="text-input" id="territorio-tipo" required value={form.tipo} onChange={(e) => setForm({ ...form, tipo: e.target.value })}>
                        <option value="">Seleccione el tipo</option>
                        {tipos.map((t) => <option key={t.clave} value={t.clave}>{t.nombre}{t.obligatorio ? ' · obligatorio' : ''}</option>)}
                    </select>
                </div>
                <div>
                    <label className="field-label" htmlFor="territorio-fuente">Fuente</label>
                    <input className="text-input" id="territorio-fuente" maxLength="255" value={form.fuente} onChange={(e) => setForm({ ...form, fuente: e.target.value })} placeholder="p. ej. PT1 / GAST, ERA5-Land" />
                </div>
                <div>
                    <label className="field-label" htmlFor="territorio-licencia">Licencia</label>
                    <input className="text-input" id="territorio-licencia" maxLength="255" value={form.licencia} onChange={(e) => setForm({ ...form, licencia: e.target.value })} placeholder="Licencia de uso del archivo" />
                </div>
                <div>
                    <label className="field-label" htmlFor="territorio-fecha">Fecha de captura</label>
                    <input className="text-input" id="territorio-fecha" type="date" value={form.fecha_captura} onChange={(e) => setForm({ ...form, fecha_captura: e.target.value })} />
                </div>
                {esGeo && <div>
                    <label className="field-label" htmlFor="territorio-capa">Capa (opcional)</label>
                    <input className="text-input" id="territorio-capa" maxLength="120" value={form.capa} onChange={(e) => setForm({ ...form, capa: e.target.value })} placeholder={form.tipo === 'malla_gpkg' ? 'malla_hex_1ha_gee' : 'Nombre de la capa'} />
                </div>}
                <div>
                    <label className="field-label" htmlFor="territorio-sha">SHA-256 declarado (opcional)</label>
                    <input className="text-input" id="territorio-sha" maxLength="64" value={form.sha256_declarado} onChange={(e) => setForm({ ...form, sha256_declarado: e.target.value.trim() })} placeholder="Para comprobar que el archivo llegó completo" />
                </div>
            </div>
            {form.tipo === 'otro' && <>
                <label className="field-label" htmlFor="territorio-descripcion">Descripción</label>
                <input className="text-input" id="territorio-descripcion" required maxLength="255" value={form.descripcion} onChange={(e) => setForm({ ...form, descripcion: e.target.value })} />
            </>}
            <label className="check-line"><input type="checkbox" checked={form.es_sensible} onChange={(e) => setForm({ ...form, es_sensible: e.target.checked })} /> Contiene información sensible</label>

            {progreso !== null && <div className="upload-progress" role="progressbar" aria-valuenow={progreso} aria-valuemin="0" aria-valuemax="100"><span style={{ width: `${progreso}%` }} /><small>{progreso < 100 ? `Subiendo… ${progreso}%` : 'Validando en el servidor…'}</small></div>}

            <div className="modal-actions">
                <button className="primary-button" type="submit" disabled={!form.archivo || !form.tipo || progreso !== null}><UploadCloud size={18} /> {progreso !== null ? 'Subiendo…' : 'Subir y validar'}</button>
            </div>
        </form>
    );
}
