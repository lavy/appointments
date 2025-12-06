<?php

use App\Http\Controllers\WhatsappWebhookController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\FacebookMessengerWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/whatsapp/webhook', [WhatsappWebhookController::class, 'handle']);
Route::get('/whatsapp/webhook', [WhatsappWebhookController::class, 'verify']);
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);
Route::post('/messenger/webhook', [FacebookMessengerWebhookController::class, 'handle']);
Route::get('/messenger/webhook', [FacebookMessengerWebhookController::class, 'verify']);
