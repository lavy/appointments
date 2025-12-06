# Venezuela Tecnológica – WhatsApp Bot (Laravel)

Este repositorio contiene el proyecto Laravel en `appointments/`. Para instalar dependencias de Node o PHP debes entrar a esa carpeta, porque ahí es donde viven los archivos `package.json` y `composer.json`.

## Arranque rápido del bot de WhatsApp
1. Ve al directorio del proyecto Laravel:
   ```bash
   cd appointments
   ```
2. Instala las dependencias de Node en esa ruta (si corres `npm install` fuera de esta carpeta verás el error `ENOENT: no such file or directory, open .../package.json`).
   ```bash
   npm install
   ```
3. Inicia el bot y escanea el QR que aparece en la terminal:
   ```bash
   npm run bot:whatsapp
   ```
4. La sesión se guarda en `storage/whatsapp/` dentro de la carpeta `appointments/`. Si necesitas reiniciar el enlace, borra el contenido de ese directorio y vuelve a ejecutar el paso anterior.

## Estructura del repo
- `appointments/`: código Laravel y el bot de WhatsApp.
- `README.md` (este archivo): notas rápidas para ubicar la carpeta correcta al instalar y ejecutar el bot.

Si recibes un error similar al de la captura (`ENOENT` al buscar `package.json`), verifica que estés ejecutando los comandos desde `appointments/`.
appointments/.env.example
+4
-0

@@ -15,45 +15,49 @@ DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

WHATSAPP_VERIFY_TOKEN=
WHATSAPP_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

VITE_APP_NAME="${APP_NAME}"
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
appointments/README.md
+51
-0

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## MVP: turnos por WhatsApp + panel web

Primer corte funcional siguiendo la visión de “WhatsApp primero”. El flujo completo es:

- WhatsApp Cloud API envía mensajes entrantes al webhook `POST /api/whatsapp/webhook`.
- Laravel identifica el cliente y lo guía por un flujo lineal: menú (1 = pedir turno), pedir fecha, pedir hora, verificar disponibilidad, crear la cita y confirmar por WhatsApp.
- El negocio puede ver y actualizar el estado de los turnos del día en `/dashboard`.

### Configuración

1. Copia `.env.example` a `.env` y agrega las credenciales de WhatsApp Cloud:
   - `WHATSAPP_VERIFY_TOKEN`
   - `WHATSAPP_TOKEN`
   - `WHATSAPP_PHONE_NUMBER_ID`
2. Ejecuta migraciones y seed (crea usuario admin@example.com / password y un negocio de prueba “Venezuela Tecnológica”):
   ```bash
   php artisan migrate --seed
   ```
3. Expone el webhook en Meta Developers apuntando a `https://tu-dominio.com/api/whatsapp/webhook` con el mismo `WHATSAPP_VERIFY_TOKEN`.
4. Inicia sesión en la app (usa tu stack de auth preferido o crea manualmente el login) y visita `/dashboard` para ver y actualizar los turnos de hoy.

### Flujo conversacional mínimo (Cloud API)

- Cualquier mensaje: muestra menú: “1️⃣ para pedir un turno / 2️⃣ ver o cancelar”.
- “1” → pide fecha (AAAA-MM-DD) → pide hora (HH:MM) → crea cita si el slot está libre.
- “2” → responde que el panel web es la vía actual para ver/cancelar.

### Panel web

- Ruta: `/dashboard` (requiere middleware `auth`).
- Lista los turnos del día del negocio asociado al usuario.
- Selector para cambiar estado: pending, confirmed, completed o canceled.

## Bot de WhatsApp (Venezuela Tecnológica)

Se mantiene el bot local con [`whatsapp-web.js`](https://github.com/pedroslopez/whatsapp-web.js) para pruebas rápidas vía WhatsApp Web.

### Configuración rápida

1. Instala Node.js 18+ y desde la carpeta del proyecto (`appointments/`) ejecuta `npm install` para obtener las dependencias (`whatsapp-web.js` y `qrcode-terminal`). Si corres el comando desde una carpeta superior verás un error `ENOENT` porque no encontrará `package.json`.
2. Ejecuta `npm run bot:whatsapp` (se inicia en modo headless con `puppeteer`).
3. Escanea el código QR que se mostrará en la terminal con la aplicación de WhatsApp para vincular la sesión. La sesión queda guardada en `storage/whatsapp` para que no tengas que re-escanear en futuros arranques.
4. Opcional: borra el contenido de `storage/whatsapp` si necesitas reiniciar el enlace.

### Respuestas disponibles

- `hola`, `hola!`, `hola bot`: saludo inicial.
- `cita` o `citas`: instrucciones para agendar una cita.
- `help` o `ayuda`: muestra los comandos disponibles.
- Cualquier otro mensaje recibe una confirmación de recepción.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**