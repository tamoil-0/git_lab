# PSEC UNAP

Portal web de la Direccion de Proyeccion Social y Extension Cultural, construido con Laravel 12, Breeze, Inertia, React, Vite y Tailwind CSS.

## Origen del proyecto

El proyecto se inicio desde una instalacion limpia de Laravel 12:

```bash
composer create-project laravel/laravel website "12.*"
cd website
composer require laravel/breeze --dev
php artisan breeze:install react
```

Despues se adapto la base generada por Breeze para implementar el portal institucional, paginas publicas, datos iniciales, estilos y flujo de deploy con GitLab CI/CD.

## Stack

- Laravel 12
- Laravel Breeze
- Inertia Laravel
- React 18
- Vite 7
- Tailwind CSS
- MySQL/MariaDB

## Requisitos

- PHP 8.2 o superior
- Composer
- Node.js 20 o superior
- MySQL o MariaDB

## Instalacion local

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
```

Para levantar el entorno de desarrollo:

```bash
composer run dev
```

Ese comando inicia el servidor de Laravel, Vite, cola y logs segun la configuracion de `composer.json`.

## Verificacion

```bash
composer test
npm run build
```

## Variables de entorno

El archivo `.env` no debe subirse al repositorio. Usa `.env.example` como plantilla y configura como minimo:

- `APP_NAME`
- `APP_ENV`
- `APP_KEY`
- `APP_URL`
- `DB_CONNECTION`
- `DB_HOST`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `CACHE_STORE`
- `SESSION_DRIVER`
- variables SMTP si se enviaran correos

En produccion, `APP_URL` debe usar HTTPS:

```env
APP_URL=https://psec.unap.edu.pe
```

## Base de datos

Las migraciones crean las tablas base de Laravel, autenticacion, sesiones, cache, jobs y contenido inicial del portal.

Para cargar datos iniciales:

```bash
php artisan migrate --seed
```

## Deploy con GitLab CI

El pipeline de `.gitlab-ci.yml` se ejecuta cuando se hace push a `main` y despliega por SSH ejecutando `scripts/deploy.sh` en el servidor.

Variables requeridas en GitLab: `Settings > CI/CD > Variables`.

- `PROD_HOST`
- `PROD_USER`
- `PROD_SSH_KEY`
- `PROD_PATH`

El servidor debe tener el repositorio clonado en `PROD_PATH`, un `.env` de produccion configurado, PHP, Composer, Node.js y permisos de escritura para:

- `storage`
- `bootstrap/cache`

La aplicacion Laravel debe servirse desde la carpeta `public`.

Ejemplo de root esperado en Nginx:

```nginx
root /var/www/psec/public;
index index.php;
```

## Deploy en Vercel

El proyecto incluye `vercel.json` para ejecutar Laravel con `vercel-php` y compilar los assets de Vite durante el despliegue.

Configura estas variables en Vercel, en `Project Settings > Environment Variables`:

```env
APP_NAME="Proyeccion Social UNAP"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...
APP_URL=https://tu-dominio.vercel.app
LOG_CHANNEL=stderr
CACHE_STORE=array
SESSION_DRIVER=cookie
QUEUE_CONNECTION=sync
```

Genera `APP_KEY` localmente con:

```bash
php artisan key:generate --show
```

Si quieres mostrar contenido desde las tablas del portal, Vercel debe conectarse a una base de datos remota. No uses `DB_HOST=127.0.0.1` en Vercel, porque apunta a la propia funcion serverless. Configura las credenciales reales de MySQL/PostgreSQL o deja las variables `DB_*` sin publicar si solo necesitas verificar que la interfaz cargue con datos de respaldo.

## Archivos sensibles

No se deben versionar:

- `.env`
- claves privadas
- `vendor`
- `node_modules`
- `public/build`
- archivos temporales de diagnostico o setup
