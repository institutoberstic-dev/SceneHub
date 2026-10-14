# Auditoría de SceneHub · Fase 1A (solo lectura)

Proyecto GuajiraTech · Base de datos para la ANH · encargo v9
Fecha: 14-oct-2026 · Alcance: repositorio local `C:\xampp\htdocs\SceneHub` (copia de trabajo de la rama `main`)
Estado: **COMPUERTA 1A — pendiente de aprobación del integrador.** No se modificó ningún archivo de código, configuración, migración ni dato.

> Convención: **Verificado** = leído en el código o comprobado con un comando · **Inferido** = razonado a partir del código, sin ejecución · **Pendiente** = necesita información o una acción externa (servidor, Unity, ANH).
> Las referencias son `archivo:línea` o `comando → salida`.

---

## 0. Cómo se hizo la auditoría (y qué limita sus conclusiones)

- Esta sesión **no tiene terminal en el PC** del proyecto. Se copiaron a un entorno aislado (solo lectura) los archivos versionados de `app/`, `bootstrap/`, `config/`, `database/`, `docs/*.md`, `resources/`, `routes/`, `tests/` y la raíz, más los metadatos de `.git` (HEAD, refs, reflog e índice) y los 4 archivos del escenario local. No se copiaron `vendor/`, `node_modules/`, `public/fontawesome/`, `docs/informes/*.docx` ni `api_gd.zip`.
- Del `.env` **solo se leyeron los nombres** de las variables y tres valores no secretos (`APP_ENV`, `APP_DEBUG`, `DB_CONNECTION`). La copia se borró después.
- **No se pudo ejecutar** `php artisan test`, `npm run build`, `composer audit` ni `npm audit` con las dependencias reales: el entorno no tiene acceso a Packagist ni a npm. La línea base de pruebas queda **Pendiente** (sección A7, con los comandos para correrla en el PC).
- Para el mapa de Unity se revisó, con permiso de solo lectura, `D:\Backups\GuajiraTechPre-Alpha-PC\Assets`. No se encontró ningún consumo de la API actual (ver `MAPA_UNITY.md`).

---

## Resumen (15 líneas)

1. Stack: Laravel 12 sobre PHP ^8.2 y una **SPA** en React 19, React Router 7, Vite 7 y Tailwind 4. **Sin Inertia ni SSR.**
2. No hay JavaScript en el servidor: Node solo compila (`npm run build`). Producción sirve PHP y estáticos.
3. Despliegue manual, sin CI: git pull + composer + build + migrate. `public/build` no se versiona.
4. MariaDB en producción (MySQL en local). Roles y permisos con Spatie. La zona horaria se fuerza a `America/Bogota` en `AppServiceProvider`.
5. La API pública (`routes/api.php`) no tiene autenticación ni token. Hay 11 GET y 1 POST (imágenes de Unity).
6. **Privacidad:** `/api/escenarios[/{id}]` expone correos del dueño y de los miembros, nombres de quienes suben archivos y rutas internas. `/api/emociones` publica registros de emoción cuadro por cuadro.
7. Los archivos de escenario viven en el disco `public`. Si existe `storage:link` (Unity lo requiere para las imágenes), se pueden descargar sin sesión.
8. Versionado de datos: `data_versions` guarda un SHA-256 de las **filas normalizadas**, no del archivo. La versión es la cadena «1.0», «1.1»…
9. **No hay inmutabilidad:** borrar un escenario elimina en cascada el escenario, sus contenidos, versiones y resultados, además de la carpeta física.
10. El Excel de resultados tiene 4 hojas (1, 5, 10 y 60 min) y 13 o 18 columnas. El tiempo son minutos transcurridos. Se procesa en PHP al subirlo y queda en `resultados_simulacion`.
11. Las unidades solo están en el código, repetidas en 3 sitios (PHP del manifiesto, PHP de la vista y JS).
12. **No existe módulo territorial** ni manejo de GPKG, GLB o JSON geográfico (H9 confirmada).
13. Unity: la documentación dice que consume versiones-datos, simulacion, tecnologias e imágenes, pero **la copia local de Unity no las llama**. Esto es Pendiente y de riesgo alto.
14. Salud: la última suite registrada (8-oct) tenía 77 pruebas y 10 fallas de emociones, preexistentes. Hay que volver a correrla.
15. **Veredicto: «Viable con estos ajustes»** (sección «Veredicto»): privacidad de la API, inmutabilidad, catálogo de variables, descarga autenticada del Excel, módulo territorial y token de solo lectura.

---

## A0 · Estado de git — Verificado

| Dato | Valor | Evidencia |
|---|---|---|
| Rama actual | `main` | `.git/HEAD` → `ref: refs/heads/main` |
| Último commit en `main` | `2b9e125` · 08-oct-2026 15:29 · «Causación de nombres y documentos» | `.git/refs/heads/main`, `.git/logs/HEAD` |
| `origin/main` | `2b9e125`, igual a `main` (sin commits por subir) | `.git/refs/remotes/origin/main` |
| `dev` y `master` (locales y remotos) | `3329e23` · 02-oct-2026 · «requerimiento de simulación avanzada» | `.git/refs/heads/{dev,master}` |
| Remoto | `github.com/institutoberstic-dev/SceneHub` | `.git/config` |
| Cambios sin confirmar | **Ninguno** en los 165 archivos versionados revisados. Solo hay archivos ignorados sin seguimiento: `.env`, `.phpunit.result.cache`, `public/build/manifest.json` y los 4 archivos de `storage/app/public/escenarios/2-escenario-2/1.0/` | `git ls-files -s` sobre el índice copiado vs `git hash-object` de cada archivo (con normalización CRLF→LF por `.gitattributes: * text=auto eol=lf`) |
| No revisados | `public/fontawesome/` (5.839 archivos versionados), `docs/informes/*.docx`, `api_gd.zip` | Fuera de la copia |

Últimos commits (reflog de HEAD): 02-oct «Primer medio de conexión de fotos de unity» · 02-oct «requerimiento de simulación avanzada» · 06-oct «Se cargan invitaciones nuevas» · 06-oct «cambios de contraseña» · 08-oct «Causación de nombres y documentos».

Otros hallazgos de git:
- `git-filter-repo` se ejecutó el 22-sep-2026 (`.git/filter-repo/already_ran`): el historial fue reescrito.
- Hay un worktree registrado de otra herramienta (`.git/worktrees/api_prohydro`) que apunta fuera del repo, y referencias `refs/codex/turn-diffs/*` en `packed-refs`. Son inofensivos, pero conviene limpiarlos (Pendiente: decisión del integrador; **no se tocaron**).
- `api_gd.zip` (31 MB) está versionado en la raíz.
- **Regla para la fase 1C:** antes de cada cambio, crear una rama desde `main` (`main` está al día con `origin/main`).

---

## A1 · Mapa general del proyecto

### Versiones — Verificado en manifiestos; versiones instaladas Pendientes

| Componente | Declarado | Evidencia | Instalado |
|---|---|---|---|
| PHP | `^8.2` | `composer.json` `require.php` | Pendiente: `php -v` en el PC y en Hostinger |
| Laravel | `^12.0` | `composer.json` | Exacto en `composer.lock` (ver tabla de paquetes) |
| React / React DOM | `^19.1.1` | `package.json` | Exacto en `package-lock.json` |
| React Router | `^7.9.1` | `package.json` | idem |
| Vite | `^7.0.7` | `package.json` | idem |
| Node / npm | sin declarar (`engines` ausente) | `package.json` | Pendiente: `node -v`, `npm -v` |
| MariaDB | 11.8.9 en Hostinger, según `claude/decision-mariadb-scenehub-db.md` del proyecto; en local `DB_CONNECTION=mysql` (XAMPP) | `.env` (solo nombre y valor no secreto) | Pendiente: `SELECT VERSION();` en producción |

### Cómo conviven React y Laravel — Verificado

- **Puente:** no hay Inertia. `resources/views/app.blade.php` incluye `@viteReactRefresh` y `@vite([...app.css, app.jsx])`. Todas las rutas web devuelven esa vista (`routes/web.php`, fallback `Route::view('/{any}', 'app')` con `^(?!api(?:/|$)).*`).
- **Páginas React:** `resources/js/pages/*.jsx` (13 páginas) y `resources/js/components/*` (13 componentes). El enrutado está en `resources/js/app.jsx` con React Router.
- **Datos:** axios (`resources/js/http.js`) contra rutas JSON con sesión: `/escenarios-data`, `/tecnologias-data`, `/emociones-data`, etc. CSRF desde `<meta>`, con reintento en 419.
- **Compilación:** `vite build` (`package.json`) con `laravel-vite-plugin`, `@vitejs/plugin-react` y `@tailwindcss/vite`. Salida en `public/build` con `manifest.json` (ignorado en git: `.gitignore`).

### JavaScript del lado del servidor — Verificado: no existe

- No hay SSR (`vite.config.js` no define `ssr`; no hay `bootstrap/ssr`), ni un servicio Node, ni workers JS.
- `composer run dev` levanta `php artisan serve`, `queue:listen` y `vite` **solo en desarrollo**.
- La cola es de base de datos (`QUEUE_CONNECTION=database`), pero no hay jobs, eventos ni listeners en `app/`. Inferido: en producción no hace falta un worker.
- **Si Node se cae, no pasa nada en producción**: los assets ya están compilados.

### Árbol comentado — Verificado

```
SceneHub/
├─ app/
│  ├─ Http/Controllers/   EscenariosController (1.148 líneas: CRUD, contenidos, versiones, resultados),
│  │                      DataVersionsController (manifiesto y descarga para Unity), UnityImagenesController,
│  │                      EmocionesController, TecnologiasController, Login/Password/Profile, User/Roles (parciales)
│  ├─ Http/Middleware/    EnsureScenarioAccess (owner/supervisor/editor), EnsureUserIsActive, EnsureUserHasRole (sin alias)
│  ├─ Models/             Escenario (tabla «esceanarios»), EscenarioContenido, Tecnologia, CategoriaTecnologia, User, Emocion (residuo)
│  ├─ Services/           SimulationResultsImport (lector del Excel), DataVersions (SHA-256), ScenarioFiles (nombres canónicos),
│  │                      ScenarioEmotionImport, ScenarioSolarImport (alias deprecado)
│  └─ Providers/          AppServiceProvider (zona horaria America/Bogota)
├─ bootstrap/app.php      rutas web/api/console y alias de middleware
├─ config/                estándar de Laravel + permission.php (Spatie) + firebase.php (sin uso visible)
├─ database/migrations/   26 migraciones (ver A8) · seeders: Roles, Tecnologias, Database
├─ docs/                  despliegue-hostinger.md, unity-data-api.md, solar-results-api.md, informes/*.docx
├─ public/                index.php, .htaccess, fontawesome/ (versionado), build/ (ignorado)
├─ resources/js/          SPA React (pages/, components/, http.js, app.jsx) · css/app.css (≈53 KB, único)
├─ resources/views/       app.blade.php (única vista viva) + vistas Blade antiguas sin uso
├─ routes/                web.php (sesión), api.php (público), console.php
├─ storage/app/public/    escenarios/{id}-{slug}/{versión}/… y unity/… (ignorado en git)
├─ tests/                 PHPUnit (12 archivos Feature/Unit) + 3 pruebas JS con node:test
├─ tools/                 3 scripts Python de Blender (cactus): sin relación con la plataforma
├─ Dockerfile             PHP 8.2-fpm + drivers MSSQL (heredado, sin uso)
└─ api_gd.zip             31 MB versionado
```

### Despliegue a Hostinger — Verificado (documento) / Pendiente (práctica)

- **Fuente:** `docs/despliegue-hostinger.md`. Pasos: `git pull --ff-only origin master` → `composer install --no-dev` → `npm ci && npm run build` (o compilar en local y subir `public/build`) → `php artisan migrate --force` → seeders → `optimize:clear` y cachés.
- **Ojo:** el documento hace pull de `master`, pero el trabajo reciente está en `main` (`master` va 3 commits atrás). Pendiente: confirmar con el desarrollador qué rama se publica.
- No hay CI (`.github/` no existe), FTP ni script versionado. El directorio público debe apuntar a `public/`.
- **Exclusión de la app de escritorio:** por decisión del integrador (14-oct), la app vive en un repositorio **separado** (`D:\Backups\PuentePython`), no en `puente-anh/` dentro de SceneHub. Así no llega al servidor por `git pull`. **Recomendación:** no copiar nunca la app dentro del repo de SceneHub. Si en el futuro se decide lo contrario, se excluiría con `export-ignore` en `.gitattributes` y con un paso de despliegue que borre la carpeta (Pendiente, P-12).
- Los documentos de esta auditoría (`docs/puente-anh/*.md`) sí están en el repo. Viven fuera de `public/`, así que **no son accesibles desde la web** aunque se suban por `git pull` (Inferido de `public/index.php` como raíz web).

### Variables de entorno (solo nombres) — Verificado

`APP_NAME, APP_ENV, APP_KEY, APP_DEBUG, APP_URL, APP_LOCALE, APP_FALLBACK_LOCALE, APP_FAKER_LOCALE, APP_MAINTENANCE_DRIVER, PHP_CLI_SERVER_WORKERS, BCRYPT_ROUNDS, LOG_CHANNEL, LOG_STACK, LOG_DEPRECATIONS_CHANNEL, LOG_LEVEL, DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, SESSION_DRIVER, SESSION_LIFETIME, SESSION_ENCRYPT, SESSION_PATH, SESSION_DOMAIN, BROADCAST_CONNECTION, FILESYSTEM_DISK, QUEUE_CONNECTION, CACHE_STORE, MEMCACHED_HOST, REDIS_CLIENT, REDIS_HOST, REDIS_PASSWORD, REDIS_PORT, MAIL_MAILER, MAIL_SCHEME, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION, AWS_BUCKET, AWS_USE_PATH_STYLE_ENDPOINT, VITE_APP_NAME`.
Local: `APP_ENV=local`, `APP_DEBUG=true`. En producción, `APP_DEBUG` debe ser `false` (Pendiente: verificar en Hostinger).

### Paquetes principales — Verificado (declarados)

| Paquete | Versión declarada | Licencia | Uso |
|---|---|---|---|
| laravel/framework | ^12.0 | MIT | Núcleo |
| spatie/laravel-permission | ^6.25 | MIT | Roles y permisos |
| phpoffice/phpspreadsheet | ^5.9 | MIT | Lectura de Excel |
| stechstudio/laravel-zipstream | ^5.10 | MIT | Declarado; el ZIP actual usa `ZipArchive` |
| kreait/laravel-firebase | ^6.1 | MIT | Declarado, sin uso visible en `app/` |
| laravel/tinker | ^2.10.1 | MIT | Consola |
| react, react-dom | ^19.1.1 | MIT | SPA |
| react-router-dom | ^7.9.1 | MIT | Enrutado |
| vite / laravel-vite-plugin / @vitejs/plugin-react | ^7.0.7 / ^2.0.0 / ^5.0.4 | MIT | Compilación |
| tailwindcss / @tailwindcss/vite | ^4.0.0 | MIT | Estilos |
| axios | ^1.11.0 | MIT | HTTP |
| lucide-react | ^0.468.0 | ISC | Íconos |

Versiones exactas: en `composer.lock` y `package-lock.json`. `composer audit` y `npm audit` quedan **Pendientes** (A7).

---

## A2 · Mapa funcional — Verificado

- **Rutas web** (`routes/web.php`, con sesión y `active`): login y recuperación de contraseña (con throttle), perfil, dashboard, `*-data` JSON para la SPA, CRUD de escenarios (`/escenarios-store`, `PUT /escenarios/{id}`, `POST …/contenidos`, miembros, descargas, `DELETE`), `/escenarios-analizar`, emociones (lectura, carga, miembros) y, para `role:admin`, usuarios y roles. Hay un fallback SPA.
- **Rutas API** (`routes/api.php`, públicas, sin auth): ver MAPA_UNITY.md (lista completa con estado).
- **Modelos:** `Escenario` (tabla `esceanarios`), `EscenarioContenido`, `Tecnologia`, `CategoriaTecnologia`, `User` (Spatie `HasRoles`) y `Emocion` (sin migración: residuo).
- **Servicios:** `SimulationResultsImport`, `DataVersions`, `ScenarioFiles`, `ScenarioEmotionImport`.
- **Form Requests, Policies, Resources, Jobs, Events, comandos y tareas programadas: no hay.** La validación es inline en los controladores (`$request->validate`, `Validator::make`) y la autorización usa middleware y `abort_unless` (`EscenariosController::authorizeReading`). `routes/console.php` solo trae el comando `inspire` por defecto.
- **Roles y permisos** (`RolesAndPermissionsSeeder`, `UserController::MODULE_PERMISSIONS`): roles vigentes `admin` y `usuario`. Permisos por usuario en los módulos `escenarios` (8) y `emociones` (3). Acceso por escenario en `escenarios_users.access_level` ∈ owner, supervisor, editor.
- **Manejo de archivos:** disco `public` (`storage/app/public`) para escenarios e imágenes de Unity. El disco `local` (`storage/app/private`) no se usa para escenarios.

---

## A3 · Dependencias con Unity — ver `MAPA_UNITY.md`

Resumen: según `docs/unity-data-api.md`, Unity consume `versiones-datos`, `simulacion` (y su alias `/solar`), `tecnologias`, `escenarios/{id}` y `POST /api/unity/imagenes`. **La evidencia es documental.** En la copia local del proyecto Unity no se encontró ninguna llamada a esas rutas; los comunicadores encontrados apuntan a rutas antiguas ya eliminadas de SceneHub. Hasta que el equipo de Unity lo confirme, **todo lo listado en MAPA_UNITY.md se trata como intocable**.

---

## A4 · Hipótesis H1–H11

| # | Hipótesis | Resultado | Evidencia |
|---|---|---|---|
| H1 | React + Laravel 12, PHP 8.2, MariaDB, Spatie, parte en JS | **Verificado, con precisión:** SPA React con Vite y sin Inertia. El JS solo compila; no hay SSR, servicio Node ni colas JS. MariaDB en producción (local mysql). Python 3.6.8 de Hostinger: no se usa | A1 |
| H2 | 4 endpoints públicos sin auth | **Verificado y ampliado:** son 11 GET + 1 POST públicos (emociones, escenarios, detalle, tecnologías, resultados y alias, simulación y alias, versiones) | `routes/api.php:11-26` |
| H3 | `data_versions` guarda un SHA-256 que indica cambios | **Verificado parcialmente:** el SHA-256 es de las **filas normalizadas** (`ksort` por fila, orden por intervalo y tiempo, `json_encode`), **no del archivo**. Solo crea revisión nueva si cambia | `app/Services/DataVersions.php:10-23` |
| H4 | Unidades solo en el código | **Verificado:** `DataVersionsController::UNITS`, `EscenariosController::SIMULATION_RESULT_FIELDS` y `resources/js/components/solarSampling.js` (3 copias) | archivos citados |
| H5 | La API pública expone correos, miembros y rutas | **Verificado:** `data()` y `detail()` cargan `owner:id,name,email`, `users:id,name,email` (con pivot `access_level` e `invited_by`), `contenidos.uploadedBy:id,name` y el campo `ruta`, además de `storage_directory` | `EscenariosController.php:37-43, 67-73` |
| H6 | Unity consume versiones-datos, simulacion, tecnologías e imágenes | **Pendiente (no confirmado en código):** solo hay evidencia documental. La copia local de Unity no lo hace | MAPA_UNITY.md |
| H7 | ~24 fallas conocidas en PHPUnit | **Dudoso:** el `.phpunit.result.cache` del 06-oct registra 33 pruebas con defecto sobre 77; el registro del 08-oct (documento del proyecto) dice 67 pasan y 10 fallan (emociones). Hay que volver a correr la suite | `.phpunit.result.cache`, `claude/cambios-2026-10-08-…` |
| H8 | Carga de contenidos en `storage/app/public` | **Verificado:** `EscenariosController::$storagePath = 'app/public'`, carpetas `escenarios/{id}-{slug}/{versión}/` | `EscenariosController.php:26, 1102-1118` |
| H9 | No hay módulo territorial | **Verificado:** no hay modelos, rutas, migraciones ni validaciones para GPKG, GLB o JSON geográfico. Los formatos admitidos son `xlsx, xls, doc, docx, pdf` | `EscenariosController::scenarioFileRules`, `docs/unity-data-api.md` |
| H10 | 1 escenario con 3 archivos; Excel con series de 1, 5, 10 y 60 min | **Verificado (estructura) / Pendiente (producción):** las resoluciones 1, 5, 10 y 60 están confirmadas (A12). En local hay 1 escenario (`id 2`, «escenario-2») con **4** archivos. El conteo en producción no se puede ver desde aquí | `storage/app/public/escenarios/2-escenario-2/1.0/` |
| H11 | Códigos de tecnología | **Verificado:** los 8 códigos coinciden exactamente, incluido `HABER-BOSH` con guion y sin «c» | `database/seeders/TecnologiasSeeder.php` |

---

## A5 · Semántica de los escenarios

| Pregunta | Respuesta | Estado y evidencia |
|---|---|---|
| ¿Sobre qué se calcula el SHA-256? | Sobre el arreglo de filas importadas: cada fila con claves ordenadas, filas ordenadas por (intervalo, tiempo) y luego `json_encode`. No incluye nombre de archivo ni unidades. Un Excel con otro nombre u otro orden de filas, pero los mismos valores, no crea versión | Verificado · `DataVersions.php` |
| ¿Se puede verificar ese SHA-256 desde fuera? | **No de forma fiable:** depende del formato de números de PHP (`json_encode` de floats) y de los tipos antes de guardarlos en la BD. La app de escritorio debe verificar la integridad con el **SHA-256 del archivo** (requiere un endpoint nuevo, ajuste 1B-5/7) y usar el `sha256` de `data_versions` solo como identificador de versión | Inferido |
| ¿Las versiones son inmutables? | **No.** Las filas de `data_versions` no se editan desde el código, pero: (1) `DELETE /escenarios/{id}` borra el escenario, y por `cascadeOnDelete` desaparecen `escenario_contenidos`, `data_versions` y `resultados_simulacion`, además de la carpeta física (`File::deleteDirectory`); (2) `SimulationResultsImport::persist` hace `delete()` por `archivo_id` antes de insertar | Verificado · `EscenariosController.php:571-587`, migraciones `2026_08_26_130000`, `2026_09_14_095116`, `2026_09_14_140000` |
| ¿Los id son estables? | Sí: autoincrementales, sin renumeración. `numero` es único y no se edita tras crear. **Pero** borrar y recrear un escenario genera otro `id` | Verificado · migraciones |
| ¿Borrado lógico o físico? | **Físico.** No hay `SoftDeletes` en ningún modelo | Verificado · `grep SoftDeletes app/` → sin resultados |
| ¿Zona horaria de las fechas? | `config/app.php` dice `UTC`, pero `AppServiceProvider::boot` fuerza `date_default_timezone_set('America/Bogota')`. Las marcas se escriben en hora de Bogotá **sin desfase**. Eloquent las serializa en ISO-8601 UTC (`…Z`), pero `DB::table(...)` (p. ej. `versiones[].created_at` del manifiesto) devuelve la cadena local sin zona | Inferido · `config/app.php:68`, `AppServiceProvider.php` |
| ¿Hay paginación? | No en la API de escenarios ni en la de simulación (devuelven todo). Solo hay paginación interna en emociones (`paginate(50)`) | Verificado |

---

## A6 · Exposición de datos personales o internos — Verificado

| Endpoint público | Qué expone | Riesgo |
|---|---|---|
| `GET /api/escenarios` y `/api/escenarios/{id}` | `owner{name,email}`, `users[]{name,email,pivot.access_level,pivot.invited_by}`, `contenidos[].uploaded_by/modified_by` (id) + `uploaded_by{name}`, `contenidos[].ruta` (ruta interna), `storage_directory`, `nombre_original` | **Alto**: correos y nombres de personas sin autenticación (Ley 1581) |
| `GET /api/emociones[/{meeting}]` | `data_completa[]`: un registro por cuadro (`archivo` del frame, `score_atencion`, `prob_*`, emoción, fecha y hora) y `totales.personas`. Oculta `id_persona` | **Alto**: datos derivados de análisis facial (sensibles). No entra a la base ANH, pero es público |
| Archivos en `storage/app/public/escenarios/...` | Con `public/storage` enlazado (lo exige Unity para las imágenes: `docs/despliegue-hostinger.md`), cualquier archivo de escenario se puede bajar en `/storage/escenarios/{id}-{slug}/{versión}/{archivo}`. La ruta se deduce del campo `ruta` de la API | **Alto** (Pendiente: confirmar si `public/storage` existe en producción) |
| `POST /api/unity/imagenes` | Escritura anónima en disco (throttle 30/min, 10 MB, validación de imagen) | Medio |
| `.htaccess` raíz y `config/cors.php` | `Access-Control-Allow-Origin: *` | Bajo (la API no usa cookies) |

---

## A7 · Salud del sistema

| Control | Estado | Detalle |
|---|---|---|
| PHPUnit | **Pendiente** | No se pudo ejecutar (sin vendor). Último dato: 08-oct, 77 pruebas, 10 fallas preexistentes de emociones. Comando: `php artisan test` |
| Pruebas JS | **Verificado (en la copia, Node 22)** | `node --test tests/*.test.mjs` → `tests 13 · pass 13 · fail 0`. Ojo: `node --test tests/` sin el comodín intenta cargar los `.php` y falla; usar el comodín |
| `npm run build` | **Pendiente** | Existe `public/build/manifest.json` del 08-oct (Inferido: el último build local funcionó) |
| Sintaxis PHP | **Verificado con PHP 8.3.6, no con 8.2** | `php -l` sobre los 90 archivos PHP de `app/`, `bootstrap/`, `config/`, `database/`, `routes/` y `tests/` → `files=90 errors=0`. Falta repetirlo con PHP 8.2 en el PC |
| Linter | Pendiente: `vendor/bin/pint --test`. Pint está en `require-dev`. Hay un fallo conocido en `User.php` (`ordered_imports`) |
| `composer audit` / `npm audit` | **Pendiente** (sin acceso a los registros desde aquí) |
| Deuda técnica relevante | Tabla `esceanarios` (typo); `EscenariosController` de 1.148 líneas sin Form Requests ni Policies; unidades triplicadas; `ScenarioSolarImport` deprecado; Blade antiguas sin uso; `api_gd.zip` versionado; Dockerfile MSSQL heredado; `kreait/laravel-firebase` sin uso; `master` atrasada respecto de `main` |

Comandos para que el integrador fije la línea base **en el PC** (desde `C:\xampp\htdocs\SceneHub`):

```powershell
git status
php -v ; node -v ; npm -v
php artisan test 2>&1 | Tee-Object -FilePath docs\puente-anh\linea_base_phpunit.txt
node --test tests/ 2>&1 | Tee-Object -FilePath docs\puente-anh\linea_base_js.txt
npm run build 2>&1 | Tee-Object -FilePath docs\puente-anh\linea_base_build.txt
vendor\bin\pint --test
composer audit ; npm audit --omit=dev
```

---

## A8 · Esquema de la base (desde las migraciones, sin datos) — Verificado

| Tabla | Columnas clave | Relaciones y borrado | ¿Va a la entrega ANH? |
|---|---|---|---|
| `esceanarios` | id, numero (único), nombre, owner_id, estado, descripcion, versiones decimal(10,1), storage_directory, timestamps | owner_id → users (null on delete) | Sí (sin owner_id ni storage_directory) |
| `escenarios_users` | escenario_id, user_id, access_level, invited_by | → esceanarios, users | **No** (personas) |
| `escenario_contenidos` | escenario_id, uploaded_by, modified_by, nombre, nombre_original, ruta, tipo (datos/informe/documento), mime_type, tamano, fecha_archivo, version decimal(10,1), estado | escenario → **cascade** | Solo metadatos del archivo de datos (sin usuarios ni ruta) |
| `resultados_simulacion` (antes `solar_data`) | escenario_id, archivo_id, intervalo_minutos, tiempo_minutos, 12 métricas + 5 extendidas (double) | escenario y archivo → **cascade**; único (archivo, intervalo, tiempo) | **Sí** (en formato largo) |
| `data_versions` | escenario_id, archivo_id, tipo, revision, version, sha256 | escenario y archivo → **cascade**; único (escenario, tipo, revision) | Sí |
| `tecnologias`, `categorias_tecnologia`, `escenario_tecnologia` | codigo (único), nombre, categoria_id, activo, orden | pivote: escenario cascade, tecnología restrict | Sí (catálogos) |
| `emotions`, `emotions_prom`, `emotion_members` | detalle por cuadro, promedio, accesos | — | **No** (biométricos y personas) |
| `users`, `roles`, `permissions`, `model_has_*`, `password_reset_tokens`, `sessions`, `cache`, `jobs` | estándar | — | **No** |

---

## A9 · Variables y unidades — Verificado

- **Dónde se definen:** `app/Http/Controllers/DataVersionsController.php:13-19` (`UNITS`, contrato con Unity), `app/Http/Controllers/EscenariosController.php` (`SIMULATION_RESULT_FIELDS`: nombres de la vista con la unidad en el sufijo) y `resources/js/components/solarSampling.js:1-20` (etiquetas y unidades del front). Los encabezados aceptados están en `app/Services/SimulationResultsImport.php` (`COLUMNS`).
- **17 variables** (12 del formato básico + 5 del extendido). Unidades: m3/h, W/m2, °C, m/s, W, Wh, m3, «paquetes de 10 kg», %, L.
- **Batería 500 kWh vs 48 kWh:** en el código solo aparece como dato de prueba (`500000` Wh en `tests/Feature/ScenarioSolarImportTest.php:67,117,256` y `ScenarioFileNamingTest.php:57`) y en `docs/solar-results-api.md:36` («500 kWh = 500.000 Wh»). El Excel local trae `Energía almacenada (W)` = 500.000 constante. **48 kWh no aparece en el código** (según el documento del proyecto, sale del archivo extendido). No se cambió nada.
- **`HABER-BOSH`:** `database/seeders/TecnologiasSeeder.php:35` (nombre visible «Reactor Haber-Bosch»), `tests/Feature/ScenarioTechnologiesTest.php:59` y `docs/unity-data-api.md:272`. Es contrato con Unity. No se cambió.

---

## A10 · Carga de archivos y límites del hosting

| Límite | Valor en el código | En el hosting |
|---|---|---|
| Archivos de escenario | 50 MB por archivo, hasta 10 por carga (`scenarioFileRules`) | Pendiente: `upload_max_filesize`, `post_max_size`, `max_execution_time`, `memory_limit` (hPanel → PHP Configuration, o `php -i` por SSH) |
| Imágenes de Unity | 10 MB, 10 por solicitud | idem |
| Cuota de disco | — | Pendiente (hPanel) |
| `pdo_sqlite` / `sqlite3` | Necesario para validar el GPKG en PHP (módulo territorial) | **Pendiente: `php -m | grep -i sqlite` en Hostinger.** Si no existe, la validación del GPKG en el servidor se limita a la firma del archivo (P-07) |
| Archivos del PT1 | GLB y ortofoto pueden superar 50 MB | Pendiente: tamaño real del paquete; podría necesitar carga por partes |

---

## A11 · Guía visual y de componentes de SceneHub — Verificado

- **Layout:** `components/AppLayout.jsx` (barra lateral con navegación filtrada por permiso, cabecera y cierre de sesión) y `AuthLayout.jsx` para el login.
- **Estilos:** un solo `resources/css/app.css` (≈53 KB) con Tailwind 4 y variables propias: `--blue #1261e7`, `--ink #111a30`, `--muted #667085`, `--line #e3e8f0`, `--green #199653`, `--orange #e86f12`, `--purple #6842c2`, `--cyan #168e9d`; sombras `--shadow-sm` y `--shadow-lg`; fuente Inter / system-ui.
- **Componentes reutilizables:** `Modal.jsx` (con variante `wide`), `Pagination.jsx`, `ScenarioFileDropzone.jsx` (arrastrar y soltar con casillas y detección de tipo), `TechnologySelector.jsx`, `FeedbackAlert` (en páginas), `AppErrorBoundary.jsx`, `SolarResults.jsx` (tabla y gráfico SVG). Íconos `lucide-react`.
- **Patrón de formularios:** modal con dropzone, análisis previo (`/escenarios-analizar`) y errores dentro del modal en lista.
- El módulo territorial (1B-6) debe reutilizar `Modal`, `ScenarioFileDropzone` (o un hermano), `Pagination`, `FeedbackAlert` y las clases de `app.css`. **No hay que agregar librerías.**
- Capturas: Pendientes (esta sesión no puede abrir la web local). Se pueden tomar en la fase 1B.

---

## A12 · Archivos del escenario y Excel de datos

### 1. Qué es cada archivo y cómo se reconoce — Verificado

| Archivo (local, escenario 2, v1.0) | Tipo | Cómo lo reconoce el sistema |
|---|---|---|
| `resultados escenario 2.xlsx` | `datos` (libro de resultados) | Tiene ≥ 2 de las hojas Minutos, Cada5min, Cada10min y Horas (`SimulationResultsImport::read`). Se renombra a `resultados escenario {numero}.xlsx` |
| `informe escenario 1.docx` | `informe` | El nombre contiene «informe», «inf», «reporte» o «report» (`ScenarioFiles`) |
| `Datos comunidad.xlsx` | `documento` | Excel sin esas hojas. Hojas: «Datos», «Hoja1» (Componente / Parámetro / Valor) y «Calculo demanda H» |
| `Requerimiento SceneHub.docx` | `documento` | Cualquier otro |

### 2. Estructura del Excel de resultados — Verificado (archivo local)

| Hoja | Intervalo | Filas de datos | Rango de tiempo | Notas |
|---|---|---|---|---|
| Minutos | 1 min | 1.800 | 1 → 1.800 | El encabezado dice «Hora», pero el valor son minutos |
| Cada5min | 5 min | 360 | 5 → 1.800 | |
| Cada10min | 10 min | 180 | 10 → 1.800 | |
| Horas | 60 min | 30 | 1 → 30 (horas; el importador multiplica × 60) | |

- **Columnas (formato básico, 13):** Hora · Caudal (m3/h) · Radiación solar (W/m2) · «Teperatura (°C)» (typo aceptado) · «Velocidad del viento (m/s) » (con espacio final; se normaliza) · Potencia solar (W) · Potencia neta (W) · Energía almacenada (W) [realmente Wh] · Consumo planta (W) · Agua desalinizada (m3) · Salmuera (m3) · Lodos gruesos (N.º paquetes de 10 kg) · Lodos finos (N.º paquetes de 10 kg).
- **Formato extendido (18):** agrega % estado de carga y 4 acumulados (excedente, diésel en Wh, combustible en L, demanda no cubierta).
- **Tiempo:** entero, en minutos transcurridos desde el inicio de la simulación (horizonte de 30 h). **No hay fecha calendario ni zona horaria.**
- **Celdas vacías:** 0. **Fórmulas:** 0. **Celdas combinadas:** ninguna. **Encabezados extra:** ninguno.
- Tipos: enteros en tiempo y lodos; decimales en el resto.

### 3. Procesamiento — Verificado

- Se procesa **al subirlo, en PHP** (`SimulationResultsImport::read` → `persist` → `DataVersions::register`, dentro de una transacción con bloqueo del escenario). Las filas quedan en `resultados_simulacion` y la API las lee de la BD; no relee el Excel.
- Validaciones: hojas obligatorias y no repetidas; mismas columnas en las 4 hojas; encabezados conocidos (orden libre, sin columnas desconocidas); tiempo entero, múltiplo del intervalo y sin duplicados; no negativos donde aplica; porcentaje entre 0 y 100; acumulados no decrecientes; lodos enteros; vacío → null; fórmula → valor en caché; máximo 10.000 filas por hoja.
- **Si se sube uno nuevo:** si el contenido normalizado cambia, se crea una nueva versión del escenario (carpeta `versión + 0.1`) y una nueva revisión en `data_versions` («1.1»). Si es igual, no se guarda nada.

### 4. Comparación Excel ↔ `GET /api/escenarios/{id}/simulacion` — Inferido, falta la prueba en vivo

Por el código, `muestreos."1"` debería traer 1.800 filas, `"5"` 360, `"10"` 180 y `"60"` 30, con `tiempo_minutos` = 60…1.800 en la hoja Horas. Los valores salen tal cual (double); las 5 columnas extendidas llegan como `null` en el formato básico. **Pendiente:** compararlo contra el servidor local con `php artisan serve` (fase 1B), o contra producción en solo lectura.

### 5. Plantilla para los escenarios 2 y 3 — Pendiente

No existe una plantilla oficial versionada. La estructura esperada está **implícita** en `SimulationResultsImport::COLUMNS`. Los escenarios 2 (hidrógeno) y 3 (amoniaco) traerán columnas nuevas que hoy el importador **rechaza** («sin columnas desconocidas»). Hará falta una plantilla, el catálogo de variables y una migración por formato (ajustes 1B-2 y 1B-4; P-05).

---

## Diagrama de módulos y flujo de datos

```mermaid
flowchart LR
  subgraph Equipos
    SIM[Equipo de simulación<br/>Excel de resultados]
    PT1[PT1 / GAST<br/>paquete territorial]
  end
  subgraph SceneHub["SceneHub (Hostinger · Laravel 12 + React)"]
    WEB[SPA React<br/>/escenarios, /resultados]
    CTRL[EscenariosController<br/>ScenarioFiles · SimulationResultsImport]
    DV[DataVersions<br/>SHA-256 de filas]
    DB[(MariaDB<br/>esceanarios · escenario_contenidos<br/>resultados_simulacion · data_versions<br/>tecnologias)]
    FS[[storage/app/public<br/>escenarios/… · unity/…]]
    API[API pública /api/*<br/>sin token]
    TERR[Módulo territorial<br/>NO EXISTE]:::falta
  end
  UNITY[Gemelo Unity]
  APP[App Puente de datos<br/>PC Windows · Python 3.12]
  OUT[(Archivos CSV/GPKG<br/>→ SQL Server staging)]

  SIM -->|sube con sesión| WEB --> CTRL
  CTRL --> DV --> DB
  CTRL --> FS
  PT1 -.->|hoy no hay cómo| TERR
  API --> DB
  API -->|versiones-datos, simulacion,<br/>tecnologias (según docs)| UNITY
  UNITY -->|POST /api/unity/imagenes| FS
  API -.->|futuro: API de solo lectura con token| APP --> OUT
  classDef falta fill:#fff3cd,stroke:#d39e00,color:#7a5a00;
```

---

## Veredicto: **«Viable con estos ajustes»**

SceneHub ya tiene lo esencial: escenarios versionados, un importador estricto del Excel, versiones con SHA-256, catálogo de tecnologías y una API estable. Para ser la única puerta de entrada hacia la ANH le faltan los puntos de abajo. Todos se pueden hacer de forma **aditiva** (rutas, tablas y permisos nuevos) sin tocar lo intocable de Unity.

### Faltantes

| # | Faltante | Gravedad | Esfuerzo |
|---|---|---|---|
| F1 | La API pública expone correos, nombres y rutas internas (`/api/escenarios*`) | Alta | Bajo-medio: requiere confirmar qué campos lee Unity |
| F2 | Archivos de escenario en el disco público, descargables si existe `storage:link` | Alta | Medio: moverlos al disco privado con una migración de archivos y descarga autenticada |
| F3 | No hay inmutabilidad: borrado físico en cascada de versiones y datos | Alta | Medio: retirar en vez de borrar, y restringir `destroy` |
| F4 | No hay catálogo de variables con unidad expuesto por API | Media | Bajo |
| F5 | No hay plantilla ni validación para formatos nuevos (escenarios 2 y 3) | Media | Medio |
| F6 | No hay descarga autenticada del Excel original con SHA-256 del archivo | Media | Bajo |
| F7 | No hay módulo territorial | Alta para la entrega | Alto |
| F8 | No hay API de solo lectura con token para la app | Alta para la fase 2 | Medio: no existe Sanctum; hay que decidir el mecanismo (P-08) |
| F9 | Mapa real de Unity desconocido | **Bloqueante para F1** | Depende del equipo de Unity |
| F10 | Escenarios sin `hex_id` (relación con la malla) | Baja (solo evaluar) | Bajo para evaluar |
| F11 | Emociones públicas con datos derivados de biometría | Alta (privacidad), fuera del alcance ANH | Bajo-medio; toca Unity |

### Riesgos

1. **Romper Unity sin enterarse** si se cambian campos de `/api/escenarios*` o se mueven los archivos. Mitigación: fotos de contrato (1C) y confirmación con el equipo de Unity.
2. **Pérdida de historial** por un borrado de escenario antes de que exista la inmutabilidad. Mitigación inmediata (sin código): que nadie borre escenarios publicados.
3. **Límites de Hostinger** (subida, memoria, `pdo_sqlite`) para los archivos del PT1.
4. **Desfase de rama de despliegue** (`master` frente a `main`).
5. **Suite de pruebas con fallas preexistentes**: dificulta detectar regresiones si no se fija la línea base antes.
6. **Zona horaria mixta** (cadenas locales y UTC) en las fechas de la API.
