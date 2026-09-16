# Despliegue del proyecto Laravel

No hay conexión SSH ni ruta de instalación de Hostinger configurada en este repositorio. Ejecutar estos pasos en la carpeta que contiene `artisan`, después de revisar los cambios y respaldar base de datos y archivos. La migración `normalize_emotion_tables` elimina las columnas antiguas del promedio emocional.

```sh
git pull --ff-only origin master
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`public/build` está excluido de Git: si el servidor no dispone de Node, compilar localmente y transferir esa carpeta completa, incluido `manifest.json`. Conservar `.env`, `APP_KEY` y los archivos de `storage`. El directorio público del sitio debe apuntar a `public`.

El seeder de roles conserva los accesos existentes al migrarlos al rol `usuario` y a permisos individuales por módulo. Debe ejecutarse después de la migración que agrega el estado activo de las cuentas.

No ejecutar `migrate:fresh` en el servidor: elimina los datos. Comprobar acceso al panel, carga de promedio, detalle del webinar y lectura de una versión solar después del despliegue. Las pruebas automatizadas se ejecutan localmente en SQLite en memoria.
