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
- El panel permite filtrar turnos por cualquier fecha, mostrando por defecto los turnos del día en curso.

### Configuración

1. Copia `.env.example` a `.env` y agrega las credenciales de WhatsApp Cloud:
   - `WHATSAPP_VERIFY_TOKEN`
   - `WHATSAPP_TOKEN`
   - `WHATSAPP_PHONE_NUMBER_ID`
   - (opcional) `TELEGRAM_BOT_TOKEN` para responder también por Telegram en `POST /api/telegram/webhook`
   - (opcional) `MESSENGER_VERIFY_TOKEN`, `MESSENGER_PAGE_ACCESS_TOKEN` y `MESSENGER_PAGE_ID` si quieres exponer `POST /api/messenger/webhook` para Facebook Messenger
2. Ejecuta migraciones y seed (crea usuario admin@example.com / password y un negocio de prueba “Venezuela Tecnológica”):
   ```bash
   php artisan migrate --seed
   ```
3. Expone el webhook en Meta Developers apuntando a `https://tu-dominio.com/api/whatsapp/webhook` con el mismo `WHATSAPP_VERIFY_TOKEN`.
4. Inicia sesión en `/login` con las credenciales sembradas (`admin@example.com` / `password`) y visita `/dashboard` para ver y actualizar los turnos de hoy.

### Flujo conversacional mínimo (Cloud API)

- Cualquier mensaje: muestra menú: “1️⃣ para pedir un turno / 2️⃣ ver o cancelar”. El bot responde automáticamente en español, inglés o portugués según el idioma detectado en el mensaje.
- “1” → pide fecha (AAAA-MM-DD) → pide hora (HH:MM) → crea cita si el slot está libre.
- “2” → responde que el panel web es la vía actual para ver/cancelar.

### Telegram y Facebook Messenger

- Si configuraste `TELEGRAM_BOT_TOKEN`, puedes apuntar el webhook del bot a `POST /api/telegram/webhook` y recibirás el mismo flujo conversacional y creación de turnos.
- Para Messenger, Meta validará `GET /api/messenger/webhook` con `MESSENGER_VERIFY_TOKEN` y enviará mensajes a `POST /api/messenger/webhook`; las respuestas usan la misma lógica multilenguaje.

### Panel web

- Ruta: `/dashboard` (requiere middleware `auth`).
- Lista los turnos del día del negocio asociado al usuario y permite filtrarlos por fecha.
- Selector para cambiar estado: pending, confirmed, completed o canceled.

### Recordatorios automáticos

- Comando: `php artisan appointments:send-reminders` envía un recordatorio dos días antes por el mismo canal en el que se creó el turno (WhatsApp, Telegram o Messenger).
- El comando está agendado para correr a diario en el scheduler de Laravel (`app/Console/Kernel.php`).

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
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
