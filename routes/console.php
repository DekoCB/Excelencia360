<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('whatsapp:recordatorios')->dailyAt('08:00');

Schedule::command('backup:clean')->dailyAt('01:00');
Schedule::command('backup:run')->dailyAt('01:30')->onOneServer();
Schedule::command('backup:monitor')->dailyAt('02:00');

/*
 * Sustituye a un worker persistente (php artisan queue:work) en hosting
 * compartido, donde no hay Supervisor/systemd para mantenerlo vivo: el
 * propio cron de Laravel ("* * * * * php artisan schedule:run", el único
 * cron que hay que configurar en el panel) dispara ráfagas cortas de
 * procesamiento cada minuto. --max-time=50 deja margen frente al minuto
 * completo para que no se solape con la siguiente ejecución del cron.
 * Sin esto, en un host así, las auditorías, el PDF de recibos/libretas/
 * certificados y los envíos de WhatsApp quedarían encolados para siempre.
 */
Schedule::command('queue:work --queue=default,auditoria --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();
