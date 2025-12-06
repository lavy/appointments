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
