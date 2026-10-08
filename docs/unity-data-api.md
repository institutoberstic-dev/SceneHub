# GET de datos y versiones para Unity

Las rutas globales antiguas `/api/features`, `/api/simu-solars`, `/api/send-features` y `/api/send-simu-sol` fueron eliminadas. Los clientes deben usar las API de resultados de simulación por escenario y la API emocional directa por webinar.

Las rutas públicas actuales están en `routes/api.php`:

| Método y ruta | Función |
| --- | --- |
| GET `/api/emociones` | Webinars con datos emocionales, nombre correlacionado, promedio y registros completos. |
| GET `/api/emociones/{meeting}` | Nombre y metadatos del webinar, promedio emocional y todos sus registros completos. |
| GET `/api/escenarios` | Listado de simulaciones, cada una con sus `tecnologias`. |
| GET `/api/escenarios/{escenario}` | Información, archivos y `tecnologias` participantes del escenario. |
| GET `/api/tecnologias` | Catálogo de tecnologías (`codigo`, `nombre`, `activo`). |
| GET `/api/escenarios/{escenario}/resultados-simulacion` | Resultados de simulación adaptados a las vistas del panel (alias: `/resultados`, `/resultados-solares`). |
| GET `/api/escenarios/{escenario}/simulacion` | Descarga de resultados de simulación, admite `version` (alias histórico: `/solar`). |
| GET `/api/escenarios/{escenario}/versiones-datos?tipo=simulacion` | Consulta de cambios y versiones (también acepta `tipo=solar`). |
| POST `/api/unity/imagenes` | Carga libre de imágenes desde Unity (ver «Cargar imágenes desde Unity»). |

Las APIs externas son de solo lectura, salvo `POST /api/unity/imagenes`, que solo guarda imágenes en el almacenamiento y no modifica resultados de simulación, datos emocionales ni escenarios. Las cargas de resultados y emocionales requieren sesión y permisos dentro del proyecto. El módulo Emociones guarda los archivos mediante la ruta privada `POST /emociones-data`; esta operación no se expone bajo `/api`. Usuarios y roles se consultan internamente mediante `/users-data` y `/roles-data`, con sesión y rol administrador; `/session-user` requiere sesión.

## Consultar si hay novedades

`GET /api/escenarios/ID/versiones-datos?tipo=simulacion`

El control de versiones solo aplica a resultados de simulación. `tipo=solar` es el nombre histórico y se sigue aceptando: devuelve el mismo estado y versión, con `url_datos` apuntando a `/solar`. Con `tipo=simulacion`, `url_datos` apunta a `/simulacion`. Ambas rutas entregan exactamente los mismos datos y el mismo `sha256`, así que cambiar de una a otra no obliga a Unity a volver a descargar. Los datos emocionales se consultan por webinar y no tienen versionamiento.

Respuesta orientativa:

```json
{
  "escenario_id": 1,
  "tipo": "simulacion",
  "estado": "actualizacion_disponible",
  "mensaje": "Hay una actualización disponible. Pulsa Actualizar para descargarla.",
  "hay_datos": true,
  "actualizacion_disponible": true,
  "descarga_inicial": false,
  "version": "1.1",
  "sha256": "identificador de contenido de 64 caracteres",
  "versiones": [],
  "url_datos": "/api/escenarios/1/simulacion?version=1.1"
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

## Descargar resultados de simulación

`GET /api/escenarios/ID/simulacion?version=1.0` (alias histórico: `/api/escenarios/ID/solar`; el campo `tipo` de la respuesta repite el nombre usado)

Los datos se guardan en la tabla `resultados_simulacion` (antes `solar_data`): la serie no es solo solar, incluye clima, energía, agua y residuos.

Devuelve metadatos y `muestreos` con claves `"1"`, `"5"`, `"10"` y `"60"`. Las filas contienen `tiempo_minutos`, `intervalo_minutos` y los nombres del almacenamiento:

| Campo | Unidad | Columna del Excel |
| --- | --- | --- |
| `caudal` | m3/h | Caudal (m3/h) |
| `radiacion_solar` | W/m2 | Radiación solar (W/m2) |
| `temperatura` | °C | Temperatura (°C) (también se acepta «Teperatura») |
| `velocidad_viento` | m/s | Velocidad del viento (m/s) |
| `potencia_solar` | W | Potencia solar (W) |
| `potencia_neta` | W | Potencia neta (W) |
| `energia_almacenada` | Wh | Energía almacenada (W) — el Excel la rotula W, pero el informe del Escenario 1 confirma que es Wh |
| `consumo_planta` | W | Consumo planta (W) |
| `agua_desalinizada` | m3 (acumulado) | Agua desalinizada (m3) |
| `salmuera` | m3 (acumulado) | Salmuera (m3) |
| `lodos_gruesos` | paquetes de 10 kg (acumulado) | Lodos gruesos (N.º paquetes de 10 kg) |
| `lodos_finos` | paquetes de 10 kg (acumulado) | Lodos finos (N.º paquetes de 10 kg) |
| `estado_carga` | % | % estado de carga |
| `excedente_no_aprovechado` | Wh (acumulado) | Wh acum. excedente no aprovechado |
| `energia_diesel` | Wh (acumulado) | Wh acum. entregados por el diesel |
| `combustible_diesel` | L (acumulado) | L acum. de combustible |
| `demanda_no_cubierta` | Wh (acumulado) | Wh acum. de demanda no cubierta |

Las cinco últimas pertenecen al formato extendido (18 columnas, p. ej. `resultados escenario 1 1.xlsx`). En versiones cargadas con el formato básico de 13 columnas llegan como `null`: Unity debe tratarlas como «sin dato», nunca como cero. El diccionario `unidades` acompaña cada respuesta.

La descarga devuelve la serie completa para el JSON local; la paginación es visual y no corta el archivo de Unity. La web conserva su endpoint `/api/escenarios/ID/resultados-simulacion` (alias `/resultados-solares`) con los campos adaptados a la interfaz y `version_datos` por archivo.

## Prueba manual

1. Iniciar sesión con permiso `emociones.cargar` y enviar `data_final_promedio (2).xlsx` desde el formulario privado del módulo Emociones (`POST /emociones-data`). Abrir Webinar 10: debe mostrar ATENTO y disgust, sin porcentajes. La carga emocional se relaciona únicamente mediante `id_meeting`; no crea versiones de escenarios.
2. Consultar el manifiesto sin versión local: debe devolver `descarga_inicial` y `1.0`.
3. Consultarlo con `version_actual=1.0`: debe devolver `sin_cambios` y `hay_datos=true`.
4. Cambiar un valor en una copia del Excel y cargarla en el mismo escenario: debe publicar `1.1` y avisar al cliente con `1.0`.
5. Descargar `?version=1.0` y `?version=1.1`: cada una conserva sus valores. Cambiar únicamente el nombre o el orden de las filas no publica otra versión si el contenido coincide con el último.
6. Repetir el manifiesto con `tipo=simulacion` (o `tipo=solar`). `Resultados caso 1.xlsx` alimenta las cuatro hojas (`Minutos`, `Cada5min`, `Cada10min` y `Horas`); cambiar de página o tamaño no recarga toda la pantalla.

## Cargar imágenes desde Unity

`POST /api/unity/imagenes` (multipart/form-data). API libre: no requiere sesión, permisos ni token CSRF. Limitada a 30 solicitudes por minuto por IP.

| Campo | Obligatorio | Descripción |
| --- | --- | --- |
| `imagen` | Sí, o `imagenes[]` | Un archivo de imagen. |
| `imagenes[]` | Sí, o `imagen` | Varios archivos (máximo 10 por solicitud). |
| `escenario_id` | No | Id de un escenario existente. Si se omite, se guarda en `general`. |

Formatos: `png`, `jpg`, `jpeg`, `webp`; máximo 10 MB por imagen. El servidor comprueba el contenido real del archivo (no basta con la extensión) y genera un nombre seguro y único.

Ubicación: `storage/app/public/unity/{escenario_id|general}/{AAAA-MM-DD}/{nombre}_{HHMMSS}_{aleatorio}.{ext}`. Para que la `url` de la respuesta sea accesible, ejecutar una vez `php artisan storage:link` (crea `public/storage`).

Respuesta `201`:

```json
{
  "ok": true,
  "mensaje": "Imagen guardada.",
  "escenario_id": 3,
  "total": 1,
  "imagenes": [
    {
      "nombre": "captura_142530_k3x9qa.png",
      "nombre_original": "captura.png",
      "ruta": "unity/3/2026-10-02/captura_142530_k3x9qa.png",
      "url": "http://localhost/storage/unity/3/2026-10-02/captura_142530_k3x9qa.png",
      "mime_type": "image/png",
      "tamano": 48213
    }
  ]
}
```

Errores: `422` con `ok=false`, `mensaje` y `errores` (siempre JSON, aunque Unity no envíe `Accept`); `429` si se supera el límite de solicitudes; `500` si falla la escritura (no deja archivos a medias).

Ejemplo en Unity (C#):

```csharp
IEnumerator SubirCaptura(Texture2D textura, int escenarioId)
{
    var form = new WWWForm();
    form.AddField("escenario_id", escenarioId);
    form.AddBinaryData("imagen", textura.EncodeToPNG(), "captura.png", "image/png");

    using var req = UnityWebRequest.Post(baseUrl + "/api/unity/imagenes", form);
    req.SetRequestHeader("Accept", "application/json");
    yield return req.SendWebRequest();

    if (req.result == UnityWebRequest.Result.Success)
        Debug.Log("Imagen guardada: " + req.downloadHandler.text);
    else
        Debug.LogError(req.responseCode + " " + req.downloadHandler.text);
}
```

Para varias imágenes en una sola solicitud, repetir `form.AddBinaryData("imagenes[]", bytes, nombre, mime)` por cada una. En XAMPP revisar que `upload_max_filesize` y `post_max_size` de `php.ini` admitan el tamaño total enviado.

## Consultar el escenario completo

Cada escenario representa una alternativa de simulación. Sus sucesivas cargas son revisiones de esa alternativa: se mantiene el historial y se puede consultar una versión concreta. La validación comprueba formato, columnas, hojas y tiempos; no puede determinar si los valores representan una propuesta científicamente válida. Las emociones pertenecen a meetings y conservan detalle y promedio independientes.

`GET /api/escenarios/ID` devuelve los metadatos y contenidos del escenario. Las rutas antiguas de emociones bajo escenarios y `/api/users`, `/api/roles`, `/api/me` están retiradas.

### Número de escenario y nombres de archivo

Cada escenario tiene un `numero` entero único (`"numero": 1`), independiente del `id` y del nombre visible. Se asigna al crear el escenario: el que indique el usuario o, si no, el que traiga el nombre del libro de resultados («resultados escenario 3.xlsx» ⇒ 3) cuando está libre; si no, el menor número disponible (sin escenarios ⇒ 1). Los escenarios existentes recibieron el número de su nombre («Escenario 1» ⇒ 1) o el siguiente libre.

Los archivos se guardan con nombres canónicos, sin importar cómo se llamaran al subirlos (el nombre original queda en `contenidos[].nombre_original`):

| Archivo | Se reconoce por | Nombre guardado | `contenidos[].tipo` |
| --- | --- | --- | --- |
| Libro de resultados de simulación | Hojas Minutos, Cada5min, Cada10min y Horas | `resultados escenario {numero}.xlsx` | `datos` |
| Informe | El nombre dice «informe» o «reporte», en cualquier formato admitido (Word, PDF, Excel) | `informe escenario {n}.{extensión}` | `informe` |
| Otro documento | Cualquier otro archivo (Word, PDF o Excel sin esas hojas) | Su nombre original | `documento` |

El número del informe se toma del nombre subido («Informe_esc 2.docx» ⇒ 2); si no trae número, se reutiliza el del informe con el mismo contenido o con el mismo nombre original y, si no hay, se usa el siguiente consecutivo. Volver a subir un archivo con el mismo nombre canónico y contenido distinto lo reemplaza en una versión nueva del escenario; con el mismo contenido no se guarda nada. El mismo número de informe en otro formato (.docx ⇒ .pdf) también lo reemplaza. Un informe existente solo se actualiza si el archivo subido es más reciente que el guardado: el formulario envía la fecha del archivo (`File.lastModified`, en ms) como `fechas[i]` para `archivos[i]` o `fecha_archivo` para `archivo`, y se guarda en `contenidos[].fecha_archivo`; si es igual o más antigua, ese informe no se carga y la respuesta lo indica (`accion: omitido`). Un escenario tiene un solo libro de resultados. Formatos admitidos: `.xlsx`, `.xls`, `.doc`, `.docx`, `.pdf`.

## Tecnologías participantes

Cada escenario declara qué tecnologías intervienen en la simulación. Unity usa el `codigo` para decidir qué elementos 3D, interfaces, animaciones y sonidos habilita; la asociación entre códigos y objetos 3D es responsabilidad de Unity. SceneHub no implementa ese comportamiento.

`GET /api/escenarios/ID` incluye:

```json
{
  "id": 1,
  "numero": 1,
  "nombre": "Escenario 1",
  "descripcion": "Producción de agua desalinizada",
  "tecnologias": [
    {
      "codigo": "PANEL_SOLAR",
      "nombre": "Paneles solares",
      "categoria": { "codigo": "GENERACION_ENERGIA", "nombre": "Generación de energía" }
    },
    {
      "codigo": "DIESEL",
      "nombre": "Generador diésel",
      "categoria": { "codigo": "GENERACION_ENERGIA", "nombre": "Generación de energía" }
    },
    {
      "codigo": "DESALINIZADORA",
      "nombre": "Desalinizadora",
      "categoria": { "codigo": "TRATAMIENTO_AGUA", "nombre": "Tratamiento de agua" }
    }
  ]
}
```

`GET /api/tecnologias` devuelve el catálogo completo (`codigo`, `nombre`, `categoria`, `activo`), incluidas las tecnologías desactivadas (`"activo": false`), para que Unity reconozca códigos históricos.

Cada tecnología pertenece a una categoría, también con código estable. `categoria` puede ser `null` si una tecnología nueva aún no fue clasificada.

| Categoría (código) | Código | Nombre visible |
| --- | --- | --- |
| Generación de energía (`GENERACION_ENERGIA`) | `PANEL_SOLAR` | Paneles solares |
| | `TURBINAS_EOLICAS` | Turbinas eólicas |
| | `DIESEL` | Generador diésel |
| Tratamiento de agua (`TRATAMIENTO_AGUA`) | `DESALINIZADORA` | Desalinizadora |
| Hidrógeno y amoniaco (`HIDROGENO_AMONIACO`) | `ELECTROLIZADORA` | Electrolizadora |
| | `HABER-BOSH` | Reactor Haber-Bosch |
| Valorización de residuos (`VALORIZACION_RESIDUOS`) | `GEOPOLIMEROS` | Planta de valorización de geopolímeros |
| | `MINERIA_LIQUIDA` | Planta de valorización de salmuera |

Reglas: el código es estable y no depende del nombre visible (cambiar el nombre no rompe Unity); un escenario puede tener cero, una o varias tecnologías, y una tecnología puede estar en varios escenarios; una tecnología no se borra, se desactiva. Unity debe ignorar los códigos que no reconozca.

El catálogo y sus categorías se cargan con `php artisan db:seed --class=TecnologiasSeeder` (idempotente: puede ejecutarse en cada despliegue). En la web se seleccionan con casillas al crear o actualizar el escenario; no existe campo de texto libre y el servidor rechaza cualquier código fuera del catálogo.
