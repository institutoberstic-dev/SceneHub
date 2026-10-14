# Plan de ajustes (fase 1B) y estado de la implementación (fase 1C)

Actualizado: 14-oct-2026. El integrador aprobó avanzar con el **sistema territorial** y la **API con token** (ajustes 6 y 7). Los demás ajustes siguen a la espera de las decisiones de `PREGUNTAS_PENDIENTES.md`.

| # | Ajuste | Estado | Depende de |
|---|---|---|---|
| 1 | Privacidad de la API pública | Pendiente. Las rutas nuevas ya usan lista blanca | P-01 (Unity), P-02 |
| 2 | Catálogo de variables con unidad | **Hecho (aditivo):** `config/catalogo_variables.php`, expuesto en `/api/puente/v1/catalogo-variables` | — |
| 3 | Inmutabilidad de las versiones de escenarios | Pendiente. El módulo territorial ya nace inmutable | P-04 |
| 4 | Plantilla del Excel y formatos nuevos | Pendiente | P-05 y el Excel real del escenario 2 |
| 5 | Descarga autenticada del Excel original | **Hecho (aditivo):** `/api/puente/v1/escenarios/{id}/versiones/{v}/archivo`, con `X-Content-SHA256` | — |
| 6 | Módulo territorial | **Hecho:** rama `feature/territorio-api-token` | — |
| 7 | API de solo lectura con token | **Hecho:** misma rama | — |
| 8 | Ubicación de escenarios en la malla | Solo evaluado (P-18) | Paquete real del PT1 |
| 9 | Token por cobertura en las rutas públicas (simulación y emociones) | **Hecho:** rama `feature/api-token-coberturas`, modo opcional por defecto | Activar «obligatorio» cuando Unity y el cliente de emociones envíen el token |

## Ajuste 6 · Sistema territorial

- **Problema y evidencia:** no existía ningún módulo para subir archivos geográficos (AUDITORIA A4-H9).
- **Diseño mínimo con el patrón existente:**
  - Tablas nuevas `paquete_territorial`, `archivo_territorial`, `validacion_territorial` y `registro_territorial` (migración `2026_10_14_100000`). Las FK son RESTRICT: un paquete no se puede borrar.
  - Servicios `App\Services\Territorio\PaquetesTerritoriales` (flujo) y `ValidadorTerritorial` (validaciones ligeras en PHP; usa PDO SQLite si está disponible).
  - Política `PaqueteTerritorialPolicy`.
  - Form Requests `CrearPaqueteRequest`, `SubirArchivoRequest` y `RetirarPaqueteRequest`.
  - Controlador `TerritorioController`.
  - Permisos `territorio.ver`, `territorio.subir` y `territorio.publicar`, que en Usuarios se asignan como dos módulos: «Territorio» y «Publicar territorio».
  - Interfaz React con los mismos componentes de SceneHub (`Modal`, tablas, `status-pill`, `upload-zone`): `TerritoryPage.jsx`, `TerritoryDetailPage.jsx` y `TerritoryUploader.jsx`. No se agregaron librerías.
  - Flujo: borrador → subida → validación automática → revisión humana (confirmar advertencias) → publicación → (retiro). Una versión publicada es inmutable.
  - Archivos en el **disco privado** (`storage/app/private/territorio/…`).
- **Consumidores afectados:** ninguno existente. No se tocaron rutas, controladores ni tablas que use Unity o la web actual.
- **Cómo se comprobó:**
  - `tests/Feature/TerritorioModuleTest.php` (6 pruebas: permisos, flujo completo, inmutabilidad, disco privado, cada validación, conteo con confirmación, obligatorios y versión única).
  - La suite completa conserva las mismas 10 fallas preexistentes.
- **Reversa:** `php artisan migrate:rollback --step=2` y revertir la rama.
- **Migración:** sí, solo aditiva. Requiere respaldo previo y `db:seed --class=RolesAndPermissionsSeeder`.
- **Dependencias nuevas:** ninguna.
- **Límites:** subida en una sola petición (sin carga por partes). GLB y ortofoto hasta 200 MB: hay que revisar `upload_max_filesize` y `post_max_size` en Hostinger (A10). La carga por partes queda para cuando se conozca el tamaño real del paquete del PT1.

## Ajuste 7 · API de solo lectura con token

- **Diseño (P-08, opción A):**
  - Tabla `puente_tokens`, que solo guarda el SHA-256 del token.
  - Middleware `puente.token`: Bearer, solo métodos seguros, vencimiento y revocación.
  - Comandos `puente:token-crear`, `puente:token-listar` y `puente:token-revocar`.
  - Grupo de rutas nuevo `/api/puente/v1/*` con `throttle:120,1`.
  - Resources con lista blanca (`EscenarioPuenteResource` y `PaqueteTerritorialPuenteResource`).
  - Sin Sanctum ni dependencias nuevas.
- **Consumidores afectados:** ninguno. Las rutas públicas de Unity no cambian; `PuenteApiTest::test_las_rutas_publicas_de_unity_siguen_sin_token` lo comprueba.
- **Pruebas:** `tests/Feature/PuenteApiTest.php` (9 pruebas): sin token 401; token revocado o vencido 401; solo lectura (405); el token no se guarda en claro; comandos; lista blanca sin correos ni rutas; huella del archivo; descarga por versión; catálogo alineado con `DataVersionsController::UNITS`; territorio solo publicado.
- **Contrato:** `CONTRATO_API.md`.
- **Reversa:** quitar el grupo de `routes/api.php` y el alias del middleware, y revertir la migración `2026_10_14_110000`.

## Resultados de verificación (entorno de desarrollo con las dependencias exactas de `composer.lock`, Laravel 12.35.1, PHP 8.3)

| Comprobación | Antes | Después |
|---|---|---|
| `php artisan test` | 67 pasan · 10 fallan (emociones, preexistentes) · 2 omitidas | **82 pasan** · las **mismas 10** fallan · 2 omitidas |
| Pint (`--test`) sobre los 30 archivos nuevos o tocados | — | Sin observaciones |
| `node --test tests/*.test.mjs` | 13/13 | 13/13 |
| Sintaxis de las vistas nuevas (TypeScript, solo análisis) | — | Sin errores |
| `npm run build` | — | **Pendiente en el PC** (aquí no hay registro npm) |

## Ajuste 9 · Token por cobertura en las rutas públicas

- **Pedido del integrador (14-oct):** que las rutas públicas de emociones y de resultados de simulación también puedan protegerse con token, igual que el Puente de datos.
- **Diseño:**
  - `config/api_tokens.php` define tres coberturas: `puente` (siempre obligatoria), `simulacion` y `emociones`.
  - Se reutiliza la misma tabla `puente_tokens`: la columna `alcance` guarda la cobertura (los tokens `lectura` antiguos valen para `puente`). No hay migración nueva.
  - El mismo middleware atiende los alias `puente.token` y `api.token:<cobertura>`. Se ejecuta antes de resolver el escenario: sin token responde 401, no 404.
  - `puente:token-crear` recibe `--alcance=`; `puente:token-listar` muestra la cobertura de cada token y el modo de cada cobertura.
- **Modo opcional por defecto:** sin token responde igual que antes, así que no se rompe Unity, que no se puede modificar (P-01). Si llega un token, se valida.
- **Consumidores afectados:**
  - en modo opcional, ninguno;
  - en modo obligatorio, Unity y cualquier cliente externo de emociones;
  - la web no se afecta, porque usa rutas propias con sesión (`/escenarios-data`, `/emociones-data`).
- **Pruebas:** `tests/Feature/ApiTokenCoberturaTest.php` (8 pruebas): modo opcional sin cambios; validación del token cuando se envía; los dos modos obligatorios en todas sus rutas; cobertura cruzada (403); un modo desconocido se trata como obligatorio; Puente siempre obligatorio y con tokens antiguos válidos; carga de imágenes de Unity sin cambios; comandos.
  - Suite completa: 90 pasan y fallan las mismas 10 de antes (emociones). Pint sin observaciones.
  - Comprobado también con `route:cache` y `config:cache`.
- **Reversa:**
  - inmediata: `API_TOKEN_*=opcional` y `php artisan config:cache`;
  - total: revertir la rama, sin migraciones.
