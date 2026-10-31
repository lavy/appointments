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

## Webhooks HTTP (Cloud API / Telegram / Messenger)

- WhatsApp Cloud: configura el verify token y apunta a `POST /api/whatsapp/webhook`.
- Telegram: define `TELEGRAM_BOT_TOKEN` y usa `POST /api/telegram/webhook` con el webhook del bot.
- Facebook Messenger: define `MESSENGER_VERIFY_TOKEN`, `MESSENGER_PAGE_ACCESS_TOKEN` y `MESSENGER_PAGE_ID`; Meta validará `GET /api/messenger/webhook` y enviará mensajes a `POST /api/messenger/webhook`.

## Estructura del repo
- `appointments/`: código Laravel y el bot de WhatsApp.
- `README.md` (este archivo): notas rápidas para ubicar la carpeta correcta al instalar y ejecutar el bot.

Si recibes un error similar al de la captura (`ENOENT` al buscar `package.json`), verifica que estés ejecutando los comandos desde `appointments/`.

## Acceso al panel
- Ruta de login: `appointments/public/login` (o `/login` si sirves la app desde la raíz del proyecto Laravel).
- Usuario sembrado: `admin@example.com` / `password` (creado por `php artisan migrate --seed`).

## Levantar el proyecto Laravel completo (API + panel + recordatorios)
1. Entra a `appointments/` y obtén dependencias:
   ```bash
   cd appointments
   composer install
   npm install
   ```
2. Copia `.env.example` a `.env`, genera la APP_KEY y coloca credenciales de DB y de los canales (WhatsApp Cloud, Telegram, Messenger). Ejemplo:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
3. Aplica migraciones y seed inicial (crea el usuario y el negocio de prueba):
   ```bash
   php artisan migrate --seed
   ```
4. Arranca servicios para uso diario:
   ```bash
   php artisan serve   # expone dashboard y webhooks
   npm run dev         # assets del login/dashboard
   php artisan schedule:run   # añade a cron para los recordatorios diarios
   ```
5. Webhooks activos tras el arranque (siguiendo los cambios actuales del branch):
   - WhatsApp Cloud: `POST /api/whatsapp/webhook` + `GET /api/whatsapp/webhook` (verify token).
   - Telegram: `POST /api/telegram/webhook`.
   - Messenger: `POST /api/messenger/webhook` + `GET /api/messenger/webhook` (verify token).

Si venías de otro branch/PR con conflictos, estos pasos reflejan el estado actual y deben prevalecer al desplegar.
