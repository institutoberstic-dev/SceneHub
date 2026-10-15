# Despliegue del proyecto Laravel

No hay conexión SSH ni ruta de instalación de Hostinger configurada en este repositorio. **La consola SSH de Hostinger usa PHP 8.1 por defecto y el proyecto exige 8.2 o superior**: antes de `composer` o `artisan`, ejecutar `export PATH=/opt/alt/php82/usr/bin:$PATH` (o dejarlo en `~/.bash_profile`) y comprobar con `php -v`. El servidor despliega la rama `main`. Ejecutar estos pasos en la carpeta que contiene `artisan`, después de revisar los cambios y respaldar base de datos y archivos. La migración `normalize_emotion_tables` elimina las columnas antiguas del promedio emocional.

```sh
git pull --ff-only origin main
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=TecnologiasSeeder --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`public/build` está excluido de Git: si el servidor no dispone de Node, compilar localmente y transferir esa carpeta completa, incluido `manifest.json`. Conservar `.env`, `APP_KEY` y los archivos de `storage`. El directorio público del sitio debe apuntar a `public`.

El seeder de roles conserva los accesos existentes al migrarlos al rol `usuario` y a permisos individuales por módulo. Debe ejecutarse después de la migración que agrega el estado activo de las cuentas.

`TecnologiasSeeder` es idempotente: crea o actualiza las categorías y el catálogo de tecnologías por código, sin duplicar registros. Se puede ejecutar en cada despliegue.

No ejecutar `migrate:fresh` en el servidor: elimina los datos. Comprobar acceso al panel, carga de promedio, detalle del webinar y lectura de una versión de resultados de simulación después del despliegue. Las pruebas automatizadas se ejecutan localmente en SQLite en memoria.

## Despliegue de tecnologías participantes y resultados de simulación (octubre 2026)

Migraciones nuevas, en este orden:

| Migración | Efecto |
| --- | --- |
| `2026_10_02_135813_create_tecnologias_table` | Catálogo de tecnologías. |
| `2026_10_02_135822_create_escenario_tecnologia_table` | Relación escenario ↔ tecnología. |
| `2026_10_02_160000_add_energy_balance_to_solar_data` | Columnas del formato extendido (batería y diésel). |
| `2026_10_02_170000_rename_solar_data_to_resultados_simulacion` | Renombra `solar_data` a `resultados_simulacion` y `data_versions.tipo` de `solar` a `simulacion`. No mueve datos. |
| `2026_10_02_180000_create_categorias_tecnologia_table` | Categorías del catálogo y `tecnologias.categoria_id`. |

Después de migrar, ejecutar `php artisan db:seed --class=TecnologiasSeeder --force` y asignar desde la web las tecnologías de cada escenario existente (quedan sin tecnologías). Las imágenes que envía Unity requieren `php artisan storage:link` una sola vez.

Verificación rápida: `php artisan migrate:status` sin pendientes, `GET /api/tecnologias` devuelve 8 tecnologías con su categoría y `GET /api/escenarios/{id}/simulacion` (o `/solar`) devuelve los datos ya publicados con la misma versión.

Reversión, solo si es necesario: `php artisan migrate:rollback --step=5 --force` deshace las cinco migraciones (se pierden las asociaciones de tecnologías y las columnas extendidas); para volver al estado exacto, restaurar el respaldo de la base de datos.

## Despliegue del sistema territorial y de la API del Puente de datos (octubre 2026)

Cambios **aditivos**: tablas, rutas y permisos nuevos. No modifican ninguna ruta pública existente ni lo que consume Unity.

| Migración | Efecto |
| --- | --- |
| `2026_10_14_100000_create_territorio_tables` | `paquete_territorial`, `archivo_territorial`, `validacion_territorial`, `registro_territorial`. |
| `2026_10_14_110000_create_puente_tokens_table` | `puente_tokens` (solo guarda el SHA-256 de cada token). |

Pasos, con respaldo previo de la base de datos:

```sh
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force   # crea territorio.ver, territorio.subir y territorio.publicar
php artisan config:cache && php artisan route:cache
npm run build   # o compilar en local y subir public/build
```

- Asignar desde **Usuarios** los módulos «Territorio» (subir y validar) y «Publicar territorio» (revisar, publicar y retirar).
- Los archivos territoriales se guardan en `storage/app/private/territorio/…` (disco privado): **no** quedan accesibles en `/storage`.
- Límites del servidor: GLB y ortofoto admiten hasta 200 MB en la aplicación; `upload_max_filesize` y `post_max_size` de PHP deben ser iguales o mayores (hPanel → Configuración de PHP).
- Validación interna del GeoPackage: requiere la extensión `pdo_sqlite`. Si falta, el sistema lo indica como advertencia y la revisión completa la hace el Puente de datos.

Token para el Puente de datos (una vez, por SSH o en local contra la base de producción):

```sh
php artisan puente:token-crear "PC del proyecto" --dias=365   # muestra el token una sola vez
php artisan puente:token-listar
php artisan puente:token-revocar <id>
```

Verificación rápida: `GET /api/puente/estado` sin token responde 401 (antes era `/api/puente/v1/estado`; ver la última sección); con `Authorization: Bearer <token>` responde 200. `GET /api/escenarios` sigue respondiendo igual que antes, sin token (Unity).

Reversión: `php artisan migrate:rollback --step=2 --force` elimina las tablas nuevas (y los paquetes y tokens creados). Para conservarlos, restaurar el respaldo en lugar de revertir. Los permisos `territorio.*` pueden quedarse: no afectan a otros módulos.

## Token por cobertura en las rutas públicas de simulación y emociones (octubre 2026)

No hay migraciones. Después del `git pull`:

```sh
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
```

Las rutas públicas de `/api` quedan en dos coberturas: **simulacion** (`/api/escenarios*`, `/api/tecnologias`) y **emociones** (`/api/emociones*`). El modo de cada una se elige en el `.env`; si no se define, es `opcional`, que responde igual que antes:

```sh
API_TOKEN_SIMULACION=opcional
API_TOKEN_EMOCIONES=opcional
```

Para activar la protección de una cobertura:

1. Crear un token por cliente: `php artisan puente:token-crear "Unity gemelo" --alcance=simulacion --dias=365` (o `--alcance=emociones`).
2. Configurar el cliente para que envíe `Authorization: Bearer <token>`.
3. Confirmar con `php artisan puente:token-listar` que el token ya muestra «Último uso».
4. Cambiar a `API_TOKEN_SIMULACION=obligatorio` en el `.env` y ejecutar `php artisan config:cache`.

Desde ese momento, cualquier cliente sin token recibe **401**. `POST /api/unity/imagenes` no cambia.

**Reversa inmediata:** volver a `opcional` y ejecutar `php artisan config:cache`.

## Rutas definitivas de la API de la app: `/api/puente/…` (octubre 2026)

No hay migraciones. Las rutas pierden el `v1` y toman los nombres del encargo:

| Antes | Ahora |
| --- | --- |
| `/api/puente/v1/estado` | `/api/puente/estado` (acepta un token de cualquier cobertura) |
| `/api/puente/v1/catalogo-variables` | `/api/puente/catalogo` |
| `/api/puente/v1/escenarios` | `/api/puente/escenarios` |
| `/api/puente/v1/escenarios/{id}/versiones/{v}/archivo` | `/api/puente/escenarios/{id}/versiones/{v}/excel` |
| `/api/puente/v1/territorio/paquetes` | `/api/puente/territorio/manifiesto` (también acepta tokens `--alcance=territorio`) |
| `/api/puente/v1/territorio/archivos/{id}` | `/api/puente/territorio/archivos/{id}` |

Las rutas anteriores responden 404.

1. Después del `git pull`, ejecutar `php artisan optimize:clear`, `config:cache` y `route:cache`.
2. **En el mismo momento**, actualizar la app «Puente de datos» del PC. Si la app tiene guardado el prefijo antiguo, lo convierte sola. Los tokens existentes siguen sirviendo.

Comandos de tokens: ver la tabla «Comandos» en `docs/puente-anh/CONTRATO_API.md`. Para eliminarlos de forma definitiva: `php artisan puente:token-eliminar <id>`, o `--revocados` para limpiar los revocados y vencidos.

## Descarga de la app «Puente de datos» desde SceneHub (octubre 2026)

Hay una migración nueva y aditiva: `2026_10_14_120000_create_puente_versiones_table`, que crea la tabla `puente_versiones`. Hay también dos permisos nuevos: `puente.descargar` y `puente.publicar`.

```sh
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan optimize:clear && php artisan config:cache && php artisan route:cache
```

Después hay que **subir `public/build`** (hay pantalla nueva) y asignar los módulos en **Usuarios**:
- «Puente de datos»: ver y descargar el instalador.
- «Publicar app Puente»: subir y retirar versiones.

El administrador ya tiene ambos permisos.

- **Dónde quedan los archivos:** en `storage/app/private/puente-app/<versión>/` (disco privado). Solo se descargan con sesión y permiso.
- **Tamaño:** el instalador pesa del orden de 60 a 150 MB. Hay que revisar `upload_max_filesize` y `post_max_size` en hPanel (300M) y, si se quiere otro límite, `PUENTE_APP_MAX_MB` en `.env`.
- **Espacio en disco:** las versiones retiradas conservan su archivo. Si se necesita espacio, se borra a mano la carpeta de una versión retirada.
- **Reversa:** `php artisan migrate:rollback --step=1 --force` elimina la tabla. Los archivos se borran a mano en `storage/app/private/puente-app/`.

## Token para el catálogo de webinars de Berstic Live (octubre 2026)

Berstic Live también protege su API con token. SceneHub le pide la lista de webinars (títulos y fechas del módulo Emociones) a `https://bersticlive.org/api/meetings`. Ahora puede enviar ese token como `Authorization: Bearer`.

El token **lo entrega Berstic Live** y va solo en el `.env` de SceneHub, nunca en el código ni en la base de datos:

```sh
nano .env
# BERSTICLIVE_API_URL=https://bersticlive.org/api/meetings   (opcional; este es el valor por defecto)
# BERSTICLIVE_API_TOKEN=<token que entrega Berstic Live>
php artisan config:cache
```

- **Si falta el token o Berstic Live lo rechaza:** la página Emociones sigue funcionando con los webinars que ya tienen datos guardados, pero sin su título original. El administrador ve un aviso que explica qué revisar; los demás ven un aviso general. El token nunca se muestra en pantalla ni en las respuestas.
- **Sin cambios en la base de datos ni en la API pública de SceneHub.**
- **Comprobación:** con el token puesto, la página Emociones muestra los títulos reales de los webinars y no aparece el aviso amarillo.
