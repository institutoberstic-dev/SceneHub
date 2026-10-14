# Contrato de la API del Puente de datos · v1

Implementado en la rama `feature/territorio-api-token` (14-oct-2026). Código: `routes/api.php` (grupo `puente/v1`), `app/Http/Controllers/Api/PuenteController.php`, `app/Http/Resources/Puente/*`, `app/Http/Middleware/AutenticarTokenPuente.php`. Pruebas: `tests/Feature/PuenteApiTest.php`.

## Reglas

- Base: `https://<dominio>/api/puente/v1/`. **Solo GET.** Cualquier otro método con token válido responde 405.
- Autenticación: `Authorization: Bearer puente_<64 hex>`. Sin token, o con un token vencido o revocado: **401** `{"message": "Token no válido, vencido o revocado."}`.
- Tokens: se crean con `php artisan puente:token-crear "<nombre>" [--dias=N]`; el token en claro se muestra una sola vez. Se listan con `puente:token-listar` y se revocan con `puente:token-revocar <id>`. La base guarda solo el SHA-256 (tabla `puente_tokens`).
- Límite: 120 solicitudes por minuto por cliente (`throttle:120,1`). Respuestas con `Cache-Control: private, no-store`.
- **Lista blanca:** no salen correos, nombres de usuarios, miembros, dueños ni rutas internas.
- Fechas en ISO-8601 con desfase (hora de Bogotá, `-05:00`).
- Las rutas públicas existentes (`/api/escenarios`, `/api/tecnologias`, `versiones-datos`, `simulacion`, `/api/unity/imagenes`…) **no cambian** y siguen sin token (Unity).

## Rutas

| Método y ruta | Respuesta |
|---|---|
| `GET /estado` | `{ "servicio": "SceneHub", "version_api": "1", "hora_servidor": "…" }` |
| `GET /catalogo-variables` | `{ "variables": [ { clave, nombre_legible, unidad, descripcion, tipo_dato, rango_esperado, intervalo, origen_en_codigo, ambito, encabezados_excel[] } ] }`: las 17 variables de `config/catalogo_variables.php`. Sus unidades se comprueban contra `DataVersionsController::UNITS` |
| `GET /escenarios` | `{ "escenarios": [ { id, numero, nombre, descripcion, tecnologias[{codigo, nombre, categoria{codigo, nombre}}], versiones_datos[{ version, revision, sha256, publicada_en, archivo{ id, nombre, tamano, sha256_archivo, url_descarga } }] } ] }`. `sha256` es el de `data_versions` (identificador); `sha256_archivo` es el de los **bytes** del Excel, calculado en el servidor. `archivo` es `null` si el archivo ya no existe en disco |
| `GET /escenarios/{id}/versiones/{version}/archivo` | Descarga del Excel original de esa versión. Cabecera `X-Content-SHA256`. 404 si la versión no existe |
| `GET /territorio/paquetes` | `{ "paquetes": [ { id, version, estado ("publicado" o "retirado"), publicado_en, notas, archivos[{ id, tipo, nombre, tamano, sha256, crs, fuente, licencia, fecha_captura, es_sensible, capa, url_descarga }] } ] }`. Nunca incluye borradores ni paquetes validados sin publicar |
| `GET /territorio/archivos/{id}` | Descarga del archivo (cabecera `X-Content-SHA256`). 404 si pertenece a un paquete no publicado |

La app de escritorio (`D:\Backups\PuentePython`) ya implementa este contrato (`motor/cliente_api.py`) y sus datos de prueba siguen el mismo formato.

## Coberturas de token (14-oct-2026)

El mismo tipo de token protege tres coberturas, definidas en `config/api_tokens.php`. Un token sirve solo para su cobertura; si se usa en otra, la respuesta es **403**.

| Cobertura | Rutas | Modo |
|---|---|---|
| `puente` | `/api/puente/v1/*` (este contrato) | Siempre obligatorio |
| `simulacion` | `/api/escenarios*` y `/api/tecnologias` (Unity, ver `docs/unity-data-api.md`) | `API_TOKEN_SIMULACION`: `opcional` por defecto u `obligatorio` |
| `emociones` | `/api/emociones*` | `API_TOKEN_EMOCIONES`: `opcional` por defecto u `obligatorio` |

- **Crear:** `php artisan puente:token-crear "<nombre>" --alcance=puente|simulacion|emociones [--dias=N]`. Si no se indica, el alcance es `puente`.
- **Listar:** `puente:token-listar` muestra la cobertura de cada token y el modo vigente de cada cobertura.
- Los tokens creados antes de este cambio (alcance `lectura`) siguen valiendo para `puente`.
