# GET de datos y versiones para Unity

Las rutas globales antiguas `/api/features`, `/api/simu-solars`, `/api/send-features` y `/api/send-simu-sol` responden HTTP 410 con `LEGACY_ENDPOINT_RETIRED`. Sus tablas fueron retiradas del esquema. Los clientes deben usar los GET por escenario descritos aquí; una respuesta 410 no significa que el escenario carezca de datos.

Todos estos endpoints requieren la sesión autenticada existente y acceso de lectura al escenario. No son públicos. La aplicación actual usa cookies de sesión de Laravel; Unity debe conservar las cookies de su inicio de sesión y enviar `Accept: application/json`. No se ha añadido ni supuesto autenticación Bearer. Una respuesta 401/403 debe tratarse como problema de acceso, no como ausencia de datos.

## Consultar si hay novedades

`GET /api/escenarios/ID/versiones-datos?tipo=emociones_promedio`

Para Solar: `tipo=solar`. Con una copia local: añadir `version_actual=1.0` y opcionalmente `sha256_actual=HASH_GUARDADO`.

Respuesta orientativa:

```json
{
  "escenario_id": 1,
  "tipo": "emociones_promedio",
  "estado": "actualizacion_disponible",
  "mensaje": "Hay una actualización disponible. Pulsa Actualizar para descargarla.",
  "hay_datos": true,
  "actualizacion_disponible": true,
  "descarga_inicial": false,
  "version": "1.1",
  "sha256": "identificador de contenido de 64 caracteres",
  "versiones": [],
  "url_datos": "/api/escenarios/1/emociones-promedio?version=1.1"
}
```

| Estado | Acción en Unity |
| --- | --- |
| `descarga_inicial` | Descargar `url_datos` y guardar JSON y metadatos locales. |
| `sin_cambios` | Usar el JSON local. Mensaje: «Ya tienes la última versión». No borrar el JSON ni mostrar «sin datos». |
| `actualizacion_disponible` | Mostrar aviso y botón Actualizar. Descargar la versión exacta de `url_datos` solo al pulsarlo. |
| `sin_datos` | No hay datos publicados para ese escenario y módulo. Conservar cualquier copia local hasta decidir explícitamente su eliminación. |
| `version_local_posterior` | Conservar la copia local y revisar escenario/servidor; no degradar automáticamente. |

Una consulta GET no marca una versión como descargada: cada dispositivo conserva su propio estado. Guardar caché por servidor, usuario, escenario y tipo de datos. Solo actualizar la versión local después de descargar, validar y guardar el JSON completo correctamente (archivo temporal + reemplazo). En fallos de red conservar la versión anterior. Si falta el JSON local, omitir `version_actual` para volver a descargar.

Las versiones son cadenas (`"1.0"`, `"1.1"`), no floats. La revisión interna es entera. Se incrementan por escenario y tipo, solo cuando cambia el contenido normalizado del último conjunto importado. El nombre del archivo no define el formato ni provoca por sí solo una nueva versión. Subir otro contenido del mismo tipo publica el nuevo conjunto completo. La versión del paquete del escenario y la versión de datos son conceptos independientes.

`sha256` identifica el contenido normalizado por el servidor. Guardarlo como metadato; no compararlo con el hash de los bytes de la respuesta HTTP. La URL incluye la versión exacta para evitar descargar otra publicación si cambia el servidor entre consulta y descarga.

## Descargar promedio emocional

`GET /api/escenarios/ID/emociones-promedio?version=1.0`

Sin `version`, devuelve la última. Devuelve `escenario_id`, `tipo`, `version`, `sha256`, `hay_datos` y `datos` con los tres campos originales:

```json
[{"id_meeting":10,"nivel_atencion_prom":"ATENTO","emocion_ganadora_prom":"disgust"}]
```

No calcula promedios, probabilidades, puntajes ni porcentajes. Las columnas históricas permanecen en la base, pero no se fabrican valores para ellas. El nuevo Excel requiere una hoja con los tres encabezados y un registro por reunión. El detalle antiguo y los promedios numéricos anteriores siguen siendo importables, pero no generan publicaciones del nuevo formato.

## Descargar Solar

`GET /api/escenarios/ID/solar?version=1.0`

Devuelve metadatos y `muestreos` con claves `"1"`, `"5"`, `"10"`. Las filas contienen `tiempo_minutos`, `intervalo_minutos` y los nombres originales del almacenamiento: `caudal`, `radiacion_solar`, `temperatura`, `velocidad_viento`, `potencia_solar`, `potencia_neta`, `energia_almacenada`, `consumo_planta`, `agua_desalinizada`, `salmuera`, `lodos_gruesos`, `lodos_finos`. Incluye un diccionario `unidades`. La unidad de almacenamiento queda nula hasta confirmarse; el valor original se conserva.

La descarga devuelve la serie completa para el JSON local; la paginación es visual y no corta el archivo de Unity. La web conserva su endpoint `/api/escenarios/ID/resultados-solares` con los campos adaptados a la interfaz y `version_datos` por archivo.

## Prueba manual

1. Subir `data_final_promedio (2).xlsx` a un escenario. Abrir Emociones y Webinar 10: debe mostrar ATENTO y disgust, sin porcentajes.
2. Consultar el manifiesto sin versión local: debe devolver `descarga_inicial` y `1.0`.
3. Consultarlo con `version_actual=1.0`: debe devolver `sin_cambios` y `hay_datos=true`.
4. Cambiar un valor en una copia del Excel y cargarla en el mismo escenario: debe publicar `1.1` y avisar al cliente con `1.0`.
5. Descargar `?version=1.0` y `?version=1.1`: cada una conserva sus valores. Cambiar únicamente el nombre o el orden de las filas no publica otra versión si el contenido coincide con el último.
6. Repetir el manifiesto con `tipo=solar`. El Excel solar anterior alimenta las tres hojas; cambiar de página o tamaño no recarga toda la pantalla.

## Consultar el escenario completo

`GET /api/escenarios/ID/datos`

Este endpoint reúne la metadata del escenario y la publicación más reciente de cada módulo, sin repetir en la respuesta las series completas. Incluye `datos.emociones_promedio` y `datos.solar`, sus versiones, hashes, historial y URLs de descarga. También devuelve `url_actualizacion` para que un cliente administrativo pueda publicar una nueva versión.

## Publicar una actualización

`POST /api/escenarios/ID/datos/actualizar` con `multipart/form-data`:

* `archivo` (obligatorio): Excel de promedio emocional o libro Solar.
* `nombre` (opcional): nombre visible del archivo.

La ruta requiere ser owner del escenario. El servidor detecta la estructura del libro, valida sus hojas y registra la versión correspondiente. Si el contenido y el nombre almacenado coinciden con el último archivo, responde `200` con `stored: false`; si hay contenido nuevo responde `201` y `new_version: true` cuando se publica una revisión nueva. La respuesta incluye el contenido almacenado y estos indicadores para que el cliente pueda refrescar su manifiesto.

El flujo recomendado para otro proyecto es: consultar `GET /datos`, comparar la versión local con `datos.*.version`, descargar la URL exacta solo si cambió, y usar `POST /datos/actualizar` únicamente cuando ese proyecto sea el proveedor autorizado de nuevos archivos.
