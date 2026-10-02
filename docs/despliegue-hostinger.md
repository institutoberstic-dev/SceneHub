# Despliegue del proyecto Laravel

No hay conexión SSH ni ruta de instalación de Hostinger configurada en este repositorio. Ejecutar estos pasos en la carpeta que contiene `artisan`, después de revisar los cambios y respaldar base de datos y archivos. La migración `normalize_emotion_tables` elimina las columnas antiguas del promedio emocional.

```sh
git pull --ff-only origin master
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
