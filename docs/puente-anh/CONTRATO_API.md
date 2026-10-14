# Contrato de la API de la app «Puente de datos»

Actualizado: 14-oct-2026 (noche). Código: `routes/api.php` (grupo `puente`), `app/Http/Controllers/Api/PuenteController.php`, `app/Http/Resources/Puente/*`, `app/Http/Middleware/AutenticarTokenPuente.php` y `config/api_tokens.php`. Pruebas: `tests/Feature/PuenteApiTest.php` y `tests/Feature/ApiTokenCoberturaTest.php`.

Responde al ajuste 7 del encargo: *«API de solo lectura para la aplicación de escritorio, con un token limitado: escenarios, catálogo, Excel original, manifiesto territorial y descarga de archivos»*. Vive en su propio espacio, `/api/puente/…`, separado de las rutas públicas que usa Unity (regla 4: rutas nuevas antes que modificar las existentes).

> **Cambio del 14-oct (noche):**
> - El prefijo pasó de `/api/puente/v1` a `/api/puente`.
> - Se renombraron las rutas con los términos del encargo: `catalogo-variables` → `catalogo`, `…/archivo` → `…/excel` y `territorio/paquetes` → `territorio/manifiesto`.
> - Las rutas `/api/puente/v1/*` responden **404**.
> - La app se actualizó a la vez. Si encuentra guardado un prefijo antiguo (`/api/puente/v1` o `/api`), lo convierte sola.

## Reglas

- Base: `https://<dominio>/api/puente/`. **Solo GET.** Cualquier otro método con un token válido responde 405.
- Autenticación: `Authorization: Bearer puente_<64 hex>`.
  - Sin token, o con un token vencido, revocado o eliminado: **401** `{"message": "Token no válido, vencido o revocado."}`.
  - Con un token válido de otra cobertura: **403** `{"message": "Este token no cubre esta API."}`.
- Límite: 120 solicitudes por minuto por cliente. Las respuestas llevan `Cache-Control: private, no-store`.
- **Lista blanca:** no salen correos, nombres de usuarios, miembros, dueños ni rutas internas.
- Fechas en ISO-8601 con desfase (hora de Bogotá, `-05:00`).
- Los `url_descarga` son rutas **absolutas en el mismo servidor** (empiezan por `/api/puente/`) y se piden con el mismo token.

## Rutas

| Recurso del encargo | Método y ruta | Cobertura | Respuesta |
|---|---|---|---|
| Probar conexión | `GET /api/puente/estado` | Cualquiera (token vigente) | `{ "servicio": "SceneHub", "version_api": "1", "hora_servidor": "…" }` |
| Catálogo | `GET /api/puente/catalogo` | `puente` | `{ "variables": [ { clave, nombre_legible, unidad, descripcion, tipo_dato, rango_esperado, intervalo, origen_en_codigo, ambito, encabezados_excel[] } ] }`. Son las 17 variables de `config/catalogo_variables.php`; sus unidades se comprueban contra `DataVersionsController::UNITS` |
| Escenarios | `GET /api/puente/escenarios` | `puente` | `{ "escenarios": [ { id, numero, nombre, descripcion, tecnologias[{codigo, nombre, categoria{codigo, nombre}}], versiones_datos[{ version, revision, sha256, publicada_en, archivo{ id, nombre, tamano, sha256_archivo, url_descarga } }] } ] }` |
| Excel original | `GET /api/puente/escenarios/{id}/versiones/{version}/excel` | `puente` | Descarga del Excel de esa versión, con la cabecera `X-Content-SHA256`. 404 si la versión no existe |
| Manifiesto territorial | `GET /api/puente/territorio/manifiesto` | `territorio` o `puente` | `{ "paquetes": [ { id, version, estado ("publicado" o "retirado"), publicado_en, notas, archivos[{ id, tipo, nombre, tamano, sha256, crs, fuente, licencia, fecha_captura, es_sensible, capa, url_descarga }] } ] }`. Nunca incluye borradores ni paquetes validados sin publicar |
| Descarga de archivos | `GET /api/puente/territorio/archivos/{id}` | `territorio` o `puente` | Descarga del archivo, con la cabecera `X-Content-SHA256`. 404 si pertenece a un paquete no publicado |

Detalles de `escenarios`:
- `sha256` es el de `data_versions` y sirve como identificador.
- `sha256_archivo` es la huella de los **bytes** del Excel, calculada en el servidor.
- `url_descarga` tiene la forma `/api/puente/escenarios/{id}/versiones/{version}/excel`.
- `archivo` es `null` si el archivo ya no existe en disco.

En el manifiesto, `url_descarga` tiene la forma `/api/puente/territorio/archivos/{id}`.

La app de escritorio (`D:\Backups\PuentePython`) implementa este contrato (`motor/cliente_api.py` y `motor/servicio.py`), y sus datos de prueba siguen el mismo formato.

## Coberturas de token

Están en `config/api_tokens.php`. Un token sirve solo para su cobertura, salvo `/api/puente/estado`, que acepta cualquiera.

| Cobertura | Rutas | Modo |
|---|---|---|
| `puente` | Todo `/api/puente/*` | Siempre obligatorio |
| `territorio` | `/api/puente/territorio/manifiesto` y `/api/puente/territorio/archivos/{id}` | Siempre obligatorio |
| `simulacion` | Rutas públicas de Unity: `/api/escenarios*` y `/api/tecnologias` (ver `docs/unity-data-api.md`) | `API_TOKEN_SIMULACION`: `opcional` por defecto u `obligatorio` |
| `emociones` | `/api/emociones*` | `API_TOKEN_EMOCIONES`: `opcional` por defecto u `obligatorio` |

Las rutas públicas existentes (`/api/escenarios*`, `/api/tecnologias`, `/api/emociones*`) **no cambian de nombre ni de forma**, porque Unity las consume. `POST /api/unity/imagenes` sigue libre.

## Comandos (en la carpeta del proyecto)

| Para | Comando |
|---|---|
| Crear un token para la app | `php artisan puente:token-crear "PC del proyecto" --dias=365` |
| Crear un token solo de territorio | `php artisan puente:token-crear "Equipo PT1" --alcance=territorio --dias=365` |
| Crear un token para Unity o para emociones | `php artisan puente:token-crear "Unity gemelo" --alcance=simulacion --dias=365` · `--alcance=emociones` |
| Ver los tokens y el modo de cada cobertura | `php artisan puente:token-listar` |
| Anular un token (queda en el historial) | `php artisan puente:token-revocar <id>` |
| Eliminar un token definitivamente | `php artisan puente:token-eliminar <id>` (pide confirmación; `--force` la omite) |
| Eliminar todos los revocados y vencidos | `php artisan puente:token-eliminar --revocados` |

- El token en claro se muestra **una sola vez**. La base guarda solo su SHA-256.
- Sin `--dias`, el token no vence.
- Los tokens antiguos con alcance `lectura` valen para `puente`.
