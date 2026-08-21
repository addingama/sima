<?php

namespace App\Console\Commands;

use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;

class SeedDemoDataCommand extends Command
{
    protected $signature = 'sima:seed-demo
                            {--force : Izinkan di environment non-local (hati-hati)}';

    protected $description = 'Isi data demo real-case (master + transaksi) agar seluruh fitur UI bisa dicoba. Hanya untuk lokal/staging.';

    public function handle(): int
    {
        if (! $this->option('force') && ! app()->environment(['local', 'testing'])) {
            $this->error('Perintah ini hanya untuk APP_ENV=local atau testing. Pakai --force jika yakin (jangan di produksi).');

            return self::FAILURE;
        }

        if (app()->environment('production') && $this->option('force')) {
            $this->error('Ditolak: sima:seed-demo tidak boleh dijalankan di production.');

            return self::FAILURE;
        }

        $this->warn('Menjalankan DemoDataSeeder…');

        try {
            $this->call('db:seed', ['--class' => DemoDataSeeder::class, '--force' => true]);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
