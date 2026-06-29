<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Backup database MySQL/MariaDB via mysqldump.
 * Untuk produksi: jadwalkan via cron/scheduler + simpan off-site (S3/NAS).
 */
class BackupDatabase extends Command
{
    protected $signature = 'sima:backup-db
                            {--path= : Direktori output (default: storage/backups)}';

    protected $description = 'Backup database MySQL ke file .sql.gz (mysqldump).';

    public function handle(): int
    {
        if (config('database.default') !== 'mysql') {
            $this->error('Backup otomatis saat ini hanya mendukung DB_CONNECTION=mysql.');

            return self::FAILURE;
        }

        $mysqldump = trim((string) shell_exec('command -v mysqldump'));
        if ($mysqldump === '') {
            $this->error('mysqldump tidak ditemukan di PATH.');

            return self::FAILURE;
        }

        $dir = $this->option('path') ?: storage_path('backups');
        File::ensureDirectoryExists($dir);

        $filename = sprintf('sima_%s.sql.gz', now()->format('Y-m-d_His'));
        $path = $dir.'/'.$filename;

        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        $command = [
            $mysqldump,
            '-h', $host,
            '-P', (string) $port,
            '-u', $username,
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            $database,
        ];

        $process = Process::fromShellCommandline(
            implode(' ', array_map('escapeshellarg', $command)).' | gzip > '.escapeshellarg($path)
        );
        $process->setTimeout(600);
        $process->setEnv(['MYSQL_PWD' => $password]);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('Backup gagal: '.$process->getErrorOutput());

            return self::FAILURE;
        }

        $sizeKb = round(filesize($path) / 1024, 1);
        $this->info("Backup OK: {$path} ({$sizeKb} KB)");

        return self::SUCCESS;
    }
}
