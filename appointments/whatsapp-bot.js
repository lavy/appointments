import whatsapp from 'whatsapp-web.js';
import qrcode from 'qrcode-terminal';
import path from 'path';
import fs from 'fs';
import { fileURLToPath } from 'url';

const { Client, LocalAuth } = whatsapp;

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const sessionPath = path.join(__dirname, 'storage', 'whatsapp');
fs.mkdirSync(sessionPath, { recursive: true });

const client = new Client({
    authStrategy: new LocalAuth({ dataPath: sessionPath }),
    puppeteer: {
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
    },
});

client.on('qr', (qr) => {
    console.log('Escanea este código QR con WhatsApp para vincular la sesión:');
    qrcode.generate(qr, { small: true });
});

client.on('ready', () => {
    console.log('🤖 Bot de WhatsApp listo. Comandos: "hola", "cita", "ayuda".');
});

client.on('auth_failure', (message) => {
    console.error('Fallo de autenticación:', message);
});

client.on('disconnected', (reason) => {
    console.warn('Sesión desconectada:', reason);
});

client.on('message', async (message) => {
    const content = message.body?.trim().toLowerCase();

    if (content === 'hola' || content === 'hola!' || content === 'hola bot') {
        await message.reply('¡Hola! Soy el asistente de Venezuela Tecnológica. ¿En qué puedo ayudarte?');
        return;
    }

    if (content === 'cita' || content === 'citas') {
        await message.reply('Para agendar una cita, envía tu nombre y disponibilidad. Nuestro equipo te confirmará pronto.');
        return;
    }

    if (content === 'help' || content === 'ayuda') {
        await message.reply('Comandos disponibles: "hola", "cita" y "ayuda". Prueba enviando "hola" para iniciar.');
        return;
    }

    if (content) {
        await message.reply('Recibí tu mensaje. Un miembro de Venezuela Tecnológica te responderá pronto.');
    }
});

client.initialize();
