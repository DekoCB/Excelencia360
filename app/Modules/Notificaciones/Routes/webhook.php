<?php

declare(strict_types=1);

use App\Modules\Notificaciones\Http\Controllers\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('webhooks/whatsapp', [WhatsappWebhookController::class, 'verificar'])->name('webhooks.whatsapp.verificar');
Route::post('webhooks/whatsapp', [WhatsappWebhookController::class, 'recibir'])->name('webhooks.whatsapp.recibir');
