# GET de datos y versiones para Unity

Las rutas globales antiguas `/api/features`, `/api/simu-solars`, `/api/send-features` y `/api/send-simu-sol` fueron eliminadas. Los clientes deben usar las API solares por escenario y la API emocional directa por webinar.

Las rutas públicas actuales están en `routes/api.php`:

| Método y ruta | Función |
| --- | --- |
| GET `/api/emociones` | Webinars con datos emocionales, nombre correlacionado, promedio y registros completos. |
| GET `/api/emociones/{meeting}` | Nombre y metadatos del webinar, promedio emocional y todos sus registros completos. |
| GET `/api/escenarios` | Listado de simulaciones. |
| GET `/api/escenarios/{escenario}` | Información y archivos del escenario. |
| GET `/api/escenarios/{escenario}/resultados-solares` | Datos solares adaptados a las vistas del panel. |
| GET `/api/escenarios/{escenario}/solar` | Descarga de resultados solares, admite `version`. |
| GET `/api/escenarios/{escenario}/versiones-datos?tipo=solar` | Consulta de cambios y versiones solares. |

Las APIs externas son de solo lectura. Las cargas solares y emocionales requieren sesión y permisos dentro del proyecto. El módulo Emociones guarda los archivos mediante la ruta privada `POST /emociones-data`; esta operación no se expone bajo `/api`. Usuarios y roles se consultan internamente mediante `/users-data` y `/roles-data`, con sesión y rol administrador; `/session-user` requiere sesión.

## Consultar si hay novedades

`GET /api/escenarios/ID/versiones-datos?tipo=solar`

El control de versiones solo aplica a resultados solares. Los datos emocionales se consultan por webinar y no tienen versionamiento.

Respuesta orientativa:

```json
{
  "escenario_id": 1,
  "tipo": "solar",
  "estado": "actualizacion_disponible",
  "mensaje": "Hay una actualización disponible. Pulsa Actualizar para descargarla.",
  "hay_datos": true,
  "actualizacion_disponible": true,
  "descarga_inicial": false,
  "version": "1.1",
  "sha256": "identificador de contenido de 64 caracteres",
  "versiones": [],
  "url_datos": "/api/escenarios/1/solar?version=1.1"
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

## Consultar datos emocionales

`GET /api/emociones/ID_MEETING`

Devuelve JSON con los datos cargados para el webinar seleccionado. No recibe escenario ni versión. El servidor usa `id_meeting` internamente para cruzar las tablas locales con Versteeg Live, pero no expone ese identificador. Tampoco expone los campos técnicos `id` ni `id_persona`. De Versteeg Live incorpora únicamente el título y el tema; si el servicio no está disponible, usa un nombre provisional y conserva todos los datos emocionales locales.

Respuesta orientativa:

```json
{
  "webinar": {
    "titulo": "Webinar de hidrógeno",
    "tema": "Energía sostenible"
  },
  "data_promedio": [
    {
      "nivel_atencion_prom": "ATENTO",
      "emocion_ganadora_prom": "disgust",
      "source_file": "promedio.xlsx"
    }
  ],
  "data_completa": [
    {
      "archivo": "frame.jpg",
      "nivel_atencion": "ATENTO",
      "score_atencion": 0.82,
      "emocion_ganadora": "happy",
      "fecha": "2026-09-16",
      "tiempo": "10:03:05",
      "validez": true,
      "estatus_calidad_DAMA": "VERIFICADO",
      "source_file": "completo.xlsx"
    }
  ],
  "totales": {
    "promedios": 1,
    "registros": 1,
    "validos": 1,
    "invalidos": 0,
    "personas": 1
  },
  "archivos_origen": {
    "promedio": ["promedio.xlsx"],
    "datos_completos": ["completo.xlsx"]
  }
}
```

`GET /api/emociones` devuelve la misma estructura dentro del arreglo `webinars`, una entrada por cada reunión que tenga datos locales. Las claves de correlación permanecen en el servidor y no aparecen en el JSON. La API no calcula ni inventa valores. El promedio admite una hoja con los tres encabezados y un registro por reunión. El archivo completo requiere las 17 columnas de detalle del formato entregado. El promedio numérico antiguo de 11 columnas ya no se admite. Cada carga reemplaza solo su formato para los meetings incluidos, conservando el otro formato. No hay versiones emocionales.

## Descargar Solar

`GET /api/escenarios/ID/solar?version=1.0`

Devuelve metadatos y `muestreos` con claves `"1"`, `"5"`, `"10"` y `"60"`. Las filas contienen `tiempo_minutos`, `intervalo_minutos` y los nombres originales del almacenamiento: `caudal`, `radiacion_solar`, `temperatura`, `velocidad_viento`, `potencia_solar`, `potencia_neta`, `energia_almacenada`, `consumo_planta`, `agua_desalinizada`, `salmuera`, `lodos_gruesos`, `lodos_finos`. Incluye un diccionario `unidades`. La unidad de almacenamiento queda nula hasta confirmarse; el valor original se conserva.

La descarga devuelve la serie completa para el JSON local; la paginación es visual y no corta el archivo de Unity. La web conserva su endpoint `/api/escenarios/ID/resultados-solares` con los campos adaptados a la interfaz y `version_datos` por archivo.

## Prueba manual

1. Iniciar sesión con permiso `emociones.cargar` y enviar `data_final_promedio (2).xlsx` desde el formulario privado del módulo Emociones (`POST /emociones-data`). Abrir Webinar 10: debe mostrar ATENTO y disgust, sin porcentajes. La carga emocional se relaciona únicamente mediante `id_meeting`; no crea versiones de escenarios.
2. Consultar el manifiesto sin versión local: debe devolver `descarga_inicial` y `1.0`.
3. Consultarlo con `version_actual=1.0`: debe devolver `sin_cambios` y `hay_datos=true`.
4. Cambiar un valor en una copia del Excel y cargarla en el mismo escenario: debe publicar `1.1` y avisar al cliente con `1.0`.
5. Descargar `?version=1.0` y `?version=1.1`: cada una conserva sus valores. Cambiar únicamente el nombre o el orden de las filas no publica otra versión si el contenido coincide con el último.
6. Repetir el manifiesto con `tipo=solar`. `Resultados caso 1.xlsx` alimenta las cuatro hojas (`Minutos`, `Cada5min`, `Cada10min` y `Horas`); cambiar de página o tamaño no recarga toda la pantalla.

## Consultar el escenario completo

Cada escenario representa una alternativa de simulación solar. Sus sucesivas cargas son revisiones de esa alternativa: se mantiene el historial y se puede consultar una versión concreta. La validación comprueba formato, columnas, hojas y tiempos; no puede determinar si los valores representan una propuesta científicamente válida. Las emociones pertenecen a meetings y conservan detalle y promedio independientes.

`GET /api/escenarios/ID` devuelve los metadatos y contenidos del escenario. Las rutas antiguas de emociones bajo escenarios y `/api/users`, `/api/roles`, `/api/me` están retiradas.
