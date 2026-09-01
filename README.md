# SceneHub API ProHydro

Aplicación de una sola página construida con React 19 y React Router, servida por Laravel 12. Laravel expone la plantilla raíz, autenticación, sesiones, CSRF y API; Vite compila y actualiza el frontend durante el desarrollo.

## Requisitos

- PHP 8.2 o superior y Composer
- Node.js compatible con Vite 7 y npm
- La base de datos configurada en `.env`

## Instalación

```powershell
composer run setup
```

También puede hacerse manualmente:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

## Desarrollo completo

El comando recomendado inicia Laravel, la cola y Vite con recarga automática:

```powershell
composer run dev
```

Luego abre `http://127.0.0.1:8000/login`.

Para ejecutar los procesos por separado:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
npm run dev
php artisan queue:listen --tries=1
```

`php artisan pail` no forma parte del arranque en Windows porque requiere la extensión Unix `pcntl`. Los logs permanecen disponibles en `storage/logs/laravel.log`.

Laravel y Vite deben permanecer activos. Vite usa `127.0.0.1:5173`; su archivo temporal `public/hot` se crea y elimina automáticamente.

## Producción local

```powershell
npm run build
php artisan optimize:clear
php artisan serve --host=127.0.0.1 --port=8000
```

En producción no debe existir `public/hot`. Laravel cargará los archivos versionados de `public/build`.

## Pruebas

```powershell
composer run test
npm run build
```

La plantilla Blade debe conservar `@viteReactRefresh` inmediatamente antes de `@vite(...)`; es necesario para React Fast Refresh en desarrollo.
