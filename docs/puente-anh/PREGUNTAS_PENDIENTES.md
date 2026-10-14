# Preguntas pendientes · Puente SceneHub → ANH

Actualizado: 14-oct-2026 (tarde).

> **Aplicado el 14-oct (tarde), solo en lo NUEVO, sin tocar lo existente:**
> - **P-08 → A**: token propio (`puente_tokens`), implementado.
> - **P-03**: los archivos territoriales nacen en el disco **privado**. Los de escenarios siguen igual hasta que se decida.
> - **P-04**: el paquete territorial nace **inmutable** (se retira, no se borra). Los escenarios siguen igual hasta que se decida.
> - **P-07**: si falta `pdo_sqlite`, el servidor lo marca como advertencia y la revisión completa la hace el Puente.
> - **P-13 → A** y **P-16 → A**: aplicados en la API nueva.
>
> Siguen abiertas: P-01, P-02, P-04 (escenarios), P-05, P-06, P-09, P-10, P-11, P-14, P-15, P-17 y P-18. Marca con `[x]` la opción elegida y devuelve el archivo; se retoma solo con las respuestas.

---

### P-01 · Qué consume realmente Unity de SceneHub
- Contexto: `docs/unity-data-api.md` y `routes/api.php:17` dicen que Unity consume versiones-datos, simulacion (y `/solar`), tecnologias e imágenes. En la copia local del proyecto Unity (`D:\Backups\GuajiraTechPre-Alpha-PC\Assets`, leída solo para consultar) no aparece ninguna de esas llamadas; solo rutas antiguas ya eliminadas (`EnergyLaravelAPICommunicator.cs:8` → `/api/send-simu-sol`). Ver MAPA_UNITY.md.
- Pregunta: ¿qué versión de Unity está vigente y qué URL y campos lee de SceneHub, en especial de `/api/escenarios` y `/api/escenarios/{id}`?
- Opciones:
  - [ ] A) El equipo de Unity entrega la lista de URL y campos (o el script C# que llama a SceneHub). Ventaja: decisión con evidencia. Riesgo bajo.
  - [ ] B) Asumir que Unity solo usa lo documentado y quitar de inmediato correos y rutas de `/api/escenarios*`. Ventaja: privacidad inmediata. Riesgo **alto** (puede romper el gemelo sin que se note desde la web).
  - [ ] C) No hacer nada por ahora. Consecuencia: la privacidad (1B-1) solo se puede aplicar en rutas nuevas.
- ⭐ Recomendada: **A**, porque la regla «lo que funciona no se rompe» exige conocer a todos los consumidores.
- Impacto si no se decide: queda bloqueado el ajuste de privacidad en las rutas existentes.
- Afecta a: Unity / web.

### P-02 · Cómo aplicar la privacidad de la API pública
- Contexto: `EscenariosController.php:37-43, 67-73` cargan `owner:id,name,email`, `users:id,name,email` (con pivot), `uploadedBy:id,name`, `ruta` y `storage_directory` en `/api/escenarios[/{id}]`.
- Pregunta: ¿cómo se retiran esos datos sin romper Unity?
- Opciones:
  - [ ] A) Crear un `JsonResource` público con lista blanca (id, numero, nombre, descripcion, estado, versión vigente, tecnologías, y archivos sin ruta ni usuarios) y usarlo en las rutas públicas existentes. Riesgo alto si Unity lee los campos retirados.
  - [ ] B) Paso 1: aplicar la lista blanca solo en las rutas **nuevas** (API de la app). Paso 2: aplicarla a las rutas existentes cuando P-01 confirme que Unity no lee esos campos. La web interna sigue usando `/escenarios-data` con sesión, sin cambios. Riesgo bajo.
  - [ ] C) No hacer nada. Consecuencia: siguen públicos los correos (Ley 1581).
- ⭐ Recomendada: **B**, porque protege lo nuevo de inmediato y deja el cambio delicado para cuando haya evidencia.
- Impacto si no se decide: la API pública sigue exponiendo datos personales.
- Afecta a: web / Unity / aplicación de escritorio.

### P-03 · Archivos de escenario en el disco público
- Contexto: los escenarios se guardan en `storage/app/public/escenarios/…` (`EscenariosController.php:26`). `docs/despliegue-hostinger.md` pide `php artisan storage:link` para las imágenes de Unity; con ese enlace, todo `storage/app/public` queda servido en `/storage/…`.
- Pregunta: ¿existe `public/storage` en producción? ¿Se mueven los escenarios a un disco privado?
- Opciones:
  - [ ] A) Mover `escenarios/` a `storage/app/private` con un comando idempotente que conserve rutas relativas (+ respaldo previo); las descargas siguen por las rutas autenticadas actuales. Riesgo medio (migración de archivos; requiere ventana).
  - [ ] B) Mantener el disco y bloquear `/storage/escenarios/*` con una regla en `public/.htaccess`. Riesgo bajo, pero es un parche que depende de Apache.
  - [ ] C) No hacer nada. Consecuencia: cualquiera que conozca o deduzca la ruta descarga los archivos.
- ⭐ Recomendada: **B ahora y A en una ventana acordada**, porque B se revierte en un minuto y no toca las imágenes de Unity (`/storage/unity/*`).
- Impacto si no se decide: exposición de archivos originales.
- Afecta a: web / Unity (solo si se tocara `unity/`).

### P-04 · Inmutabilidad: qué hacer con «Eliminar escenario»
- Contexto: `EscenariosController::destroy` (líneas 571-587) borra el escenario y su carpeta; las FK `cascadeOnDelete` eliminan contenidos, `data_versions` y `resultados_simulacion`. No hay `SoftDeletes`.
- Pregunta: ¿cómo se garantiza que una versión publicada no se pierda?
- Opciones:
  - [ ] A) Agregar `retirado_at` (migración aditiva). `destroy` marca el escenario como retirado si tiene versiones publicadas; solo se borra físicamente si no tiene datos. Las API públicas excluyen los retirados; la API de la app los muestra marcados. Riesgo medio.
  - [ ] B) Prohibir `destroy` cuando haya `data_versions` (403 con mensaje claro). Riesgo bajo, menos flexible.
  - [ ] C) No hacer nada. Consecuencia: un clic puede borrar historial que ya se cargó a la base ANH.
- ⭐ Recomendada: **B de inmediato y A en la fase del módulo territorial**, porque B es una línea reversible y A da el flujo completo «retirar, no borrar».
- Impacto si no se decide: riesgo de pérdida de historial.
- Afecta a: web / Unity (si se ocultan los retirados) / base ANH.

### P-05 · Plantilla de los escenarios 2 y 3 (columnas nuevas)
- Contexto: el importador rechaza columnas desconocidas (`SimulationResultsImport::COLUMNS`). El escenario 2 agrega hidrógeno y el 3, amoniaco, así que habrá variables nuevas.
- Pregunta: ¿cómo se aceptan variables nuevas sin cambiar el código cada vez?
- Opciones:
  - [ ] A) Catálogo de variables en BD (`variables_simulacion`: clave, encabezado, unidad, regla y formato) y tabla larga nueva `resultados_simulacion_valores` para lo que no está en la tabla ancha. La tabla ancha y su API se conservan para Unity. Riesgo medio.
  - [ ] B) Seguir con la tabla ancha: una migración y un cambio de importador por cada formato nuevo (como hasta ahora). Riesgo bajo por cambio, pero exige desarrollo para cada escenario.
  - [ ] C) No hacer nada. Consecuencia: el escenario 2 no se podrá subir.
- ⭐ Recomendada: **B para el escenario 2 si llega antes de la fase 1C; A como meta**, porque A cumple «escenarios 2 y 3 sin cambiar código», pero toca el corazón del importador. Se necesita primero el Excel real del escenario 2.
- Impacto si no se decide: bloquea la carga del escenario 2.
- Afecta a: web / Unity (si aparecen variables nuevas en `muestreos`) / aplicación / base ANH.

### P-06 · Capacidad de la batería (500 kWh o 48 kWh)
- Contexto: el código solo tiene `500000` como dato de prueba (`tests/Feature/ScenarioSolarImportTest.php:67`) y la nota de `docs/solar-results-api.md:36`. El Excel local trae 500.000 constante. 48 kWh no aparece en el código.
- Pregunta: ¿cuál es la capacidad real del escenario 1?
- Opciones: [ ] A) 500 kWh · [ ] B) 48 kWh · [ ] C) Depende del escenario (va como parámetro del escenario, no del catálogo).
- ⭐ Recomendada: **C**, y la confirma el equipo de simulación. No cambia código; afecta el rango esperado del catálogo.
- Impacto si no se decide: el rango de `energia_almacenada` en el catálogo queda abierto.
- Afecta a: base ANH / aplicación (rangos).

### P-07 · `pdo_sqlite` en Hostinger para validar el GPKG
- Contexto: el encargo pide validar en PHP `gpkg_contents`, la capa, `srs_id = 9377` y `hex_id`. Eso requiere la extensión `pdo_sqlite` o `sqlite3` en el servidor.
- Pregunta: ¿está disponible? (`php -m | findstr /i sqlite` en local; `php -m | grep -i sqlite` por SSH en Hostinger).
- Opciones:
  - [ ] A) Sí está: validación completa en el servidor.
  - [ ] B) No está: el servidor valida firma, tamaño y SHA-256, y la validación profunda la hace la app de escritorio (ya implementada allí, con `sqlite3` de Python) antes de cargar.
  - [ ] C) No hacer nada.
- ⭐ Recomendada: **verificar primero**. Si no existe, B.
- Impacto si no se decide: el diseño del validador territorial queda incompleto.
- Afecta a: web / aplicación.

### P-08 · Mecanismo del token de solo lectura para la app
- Contexto: no hay Sanctum ni Passport (`composer.json`). La regla 9 pide no agregar librerías si hay un equivalente.
- Opciones:
  - [ ] A) Tabla `puente_tokens` (hash SHA-256 del token, nombre, alcance `lectura`, `last_used_at`, `revoked_at`) + middleware `puente.token` sobre `/api/puente/*`. Se crea con un comando artisan que muestra el token una sola vez. Sin dependencias nuevas. Riesgo bajo.
  - [ ] B) Instalar `laravel/sanctum` (MIT, oficial de Laravel). Riesgo bajo, pero agrega una dependencia y migraciones propias.
  - [ ] C) No hacer nada: la app usaría la API pública. Consecuencia: no hay descarga del Excel original ni del manifiesto territorial.
- ⭐ Recomendada: **A**, porque es mínima, auditable y no agrega dependencias.
- Impacto si no se decide: bloquea la API de la app (1B-7).
- Afecta a: web / aplicación.

### P-09 · Rama que se despliega
- Contexto: `docs/despliegue-hostinger.md` hace `git pull --ff-only origin master`, pero `main` (08-oct) va 3 commits por delante de `master` (02-oct).
- Pregunta: ¿qué rama está en producción y cuál debe usarse?
- Opciones: [ ] A) `main` (actualizar el documento) · [ ] B) `master` (fusionar `main` antes del próximo despliegue) · [ ] C) No cambiar nada.
- ⭐ Recomendada: **confirmar con el desarrollador** y dejar una sola rama de despliegue documentada.
- Afecta a: web.

### P-10 · API pública de emociones (datos derivados de biometría)
- Contexto: `EmocionesController::publicEmotionPayload` publica `data_completa` (un registro por cuadro, con nombre del frame, probabilidades y hora) sin autenticación.
- Pregunta: ¿se restringe?
- Opciones:
  - [ ] A) Publicar solo promedios y totales; mover el detalle a una ruta con token. Riesgo alto si Unity usa el detalle (P-01).
  - [ ] B) Dejarlo igual y documentarlo como control planificado (criterio del 07-oct para la versión preliminar).
  - [ ] C) No hacer nada.
- ⭐ Recomendada: **B por ahora y A tras P-01**. No entra a la base ANH (lista blanca de la app).
- Afecta a: web / Unity.

### P-11 · Limpieza del repositorio
- Contexto: `api_gd.zip` (31 MB) versionado; worktree `api_prohydro` registrado y refs `refs/codex/*`; vistas Blade sin uso; `tools/*.py` de Blender; Dockerfile MSSQL.
- Opciones: [ ] A) Limpiar en una rama propia, después de las fases 1 y 2 · [ ] B) Limpiar ya · [ ] C) No hacer nada.
- ⭐ Recomendada: **A**: no aporta a la entrega ANH y no conviene mezclarlo con los cambios funcionales.
- Afecta a: web (ninguna funcionalidad).

### P-12 · Ubicación de la app de escritorio — **DECIDIDO 14-oct**
- Decisión del integrador: la app vive en un **repositorio separado** (`D:\Backups\PuentePython`, preparado para GitHub), no en `puente-anh/` dentro de SceneHub. Consecuencia: la exclusión del despliegue a Hostinger está garantizada por construcción. Los documentos del puente siguen en `docs/puente-anh/` de SceneHub (fuera de `public/`).

### P-13 · Verificación de integridad en la app
- Contexto: el `sha256` de `data_versions` es de filas normalizadas por PHP (`DataVersions.php`) y no se puede reproducir con fiabilidad fuera de PHP.
- Opciones:
  - [ ] A) La API nueva entrega el Excel original con `sha256_archivo` (calculado en el servidor sobre los bytes). La app verifica ese hash y usa `data_versions.sha256` solo como identificador de versión. Riesgo bajo.
  - [ ] B) La app recalcula el hash de filas imitando PHP. Riesgo alto de falsos «alterado».
  - [ ] C) No verificar.
- ⭐ Recomendada: **A** (ya implementada así en la app, contra datos de prueba).
- Afecta a: web / aplicación.

### P-14 · Lectura y escritura de GeoPackage en la app sin GDAL
- Contexto: el encargo sugiere `pyogrio` (necesita GDAL; en Windows funciona por wheel, pero suma ~40 MB al ejecutable). Un GPKG es una base SQLite con un formato estándar de geometría (OGC GeoPackage 1.3).
- Opciones:
  - [ ] A) Usar `sqlite3` de la biblioteca estándar: leer `gpkg_contents` y `gpkg_spatial_ref_sys`, decodificar el encabezado GPKG + WKB a WKT, y escribir el GPKG de salida con las tablas mínimas del estándar. Sin dependencias nuevas. Riesgo bajo; ya implementado y probado con datos sintéticos.
  - [ ] B) `pyogrio` (MIT) + GDAL. Lector completo, pero con dependencia pesada.
  - [ ] C) No soportar GPKG hasta tener el paquete real.
- ⭐ Recomendada: **A**, y revisar con el GPKG real del PT1 (22-oct). Si aparece algo no soportado (p. ej. curvas), pasar a B.
- Afecta a: aplicación.

### P-15 · Repositorio GitHub de la app: visibilidad y licencia
- Opciones: [ ] A) Privado, sin licencia pública (todos los derechos del proyecto) · [ ] B) Público con licencia MIT · [ ] C) Público con otra licencia que defina el área jurídica.
- ⭐ Recomendada: **A** hasta la revisión jurídica de licencias (ya pendiente en el proyecto). El repo se dejó **sin archivo LICENSE** a propósito.
- Afecta a: aplicación.

### P-16 · Zona horaria en el contrato de la API nueva
- Contexto: hoy hay mezcla de cadenas locales sin desfase y fechas UTC (AUDITORIA A5).
- Opciones: [ ] A) Las rutas nuevas devuelven ISO-8601 con desfase (`2026-10-13T18:02:00-05:00`) · [ ] B) UTC con `Z` · [ ] C) Como hoy.
- ⭐ Recomendada: **A**: es explícito y legible para el encargado. La app ya acepta A y B.
- Afecta a: web / aplicación / base ANH.

### P-17 · Origen (MEDIDO / OBSERVADO / ESTIMADO / SIMULADO) por tipo de dato
- Contexto: el encargo fija ESTIMADO para la malla del PT1. Para escenarios, la app usa SIMULADO (resultados de AnyLogic).
- Pregunta: ¿se confirma SIMULADO para series de escenarios y ESTIMADO para los indicadores de la malla?
- Opciones: [ ] A) Sí · [ ] B) Otro reparto (indicar) · [ ] C) Dejarlo abierto.
- ⭐ Recomendada: **A**.
- Afecta a: base ANH / aplicación.

### P-18 · Ubicación de los escenarios en la malla (`escenario_celda`) — solo evaluar
- Contexto: los escenarios no tienen `hex_id` (hallazgo S-15). El encargo pide solo evaluarlo.
- Opciones: [ ] A) Tabla `escenario_celda (escenario_id, hex_id, rol)` en SceneHub, editable por el dueño del escenario · [ ] B) Lista de `hex_id` declarada en el Excel (hoja nueva «Ubicacion») · [ ] C) No registrar por ahora.
- ⭐ Recomendada: **C hasta tener el paquete del PT1 publicado**, y luego A, porque necesita los `hex_id` reales.
- Afecta a: web / base ANH.
