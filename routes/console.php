<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks (ops / audit integrity)
|--------------------------------------------------------------------------
| Jalankan via: php artisan schedule:work  (dev)
| Produksi: cron * * * * * php artisan schedule:run
| Docker: service `scheduler` di docker-compose.yml
*/
Schedule::command('sima:check-balances')
    ->dailyAt('02:00')
    ->timezone(config('app.timezone'))
    ->onFailure(function () {
        logger()->critical('sima:check-balances GAGAL — drift saldo cache vs ledger.');
    });

Schedule::command('sima:prune-idempotency')
    ->dailyAt('03:00')
    ->timezone(config('app.timezone'));

Schedule::command('sima:backup-db')
    ->dailyAt('01:30')
    ->timezone(config('app.timezone'))
    ->when(fn () => config('database.default') === 'mysql')
    ->onFailure(function () {
        logger()->critical('sima:backup-db GAGAL — periksa mysqldump & kredensial DB.');
    });
