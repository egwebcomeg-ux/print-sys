<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Daily gzipped mysqldump into storage/app/backups, keeping the newest N.
 * Scheduled in routes/console.php (runs from the cPanel schedule:run cron).
 */
#[Signature('backup:database {--keep=14 : How many backups to keep}')]
#[Description('Dump the MariaDB/MySQL database to storage/app/backups')]
class BackupDatabase extends Command
{
    public function handle(): int
    {
        $config = config('database.connections.'.config('database.default'));

        if (! in_array($config['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $this->error('backup:database supports MySQL/MariaDB only.');

            return self::FAILURE;
        }

        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = $dir.DIRECTORY_SEPARATOR.$config['database'].'-'.now()->format('Y-m-d_His').'.sql';

        $process = new Process([
            env('MYSQLDUMP_PATH', 'mysqldump'),
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--user='.$config['username'],
            '--single-transaction',
            '--quick',
            '--routines',
            '--default-character-set=utf8mb4',
            '--result-file='.$file,
            $config['database'],
        ], env: ['MYSQL_PWD' => (string) $config['password']]); // keeps the password off the process list
        $process->setTimeout(600)->run();

        if (! $process->isSuccessful() || ! is_file($file) || filesize($file) === 0) {
            @unlink($file);
            $this->error('Backup failed: '.trim($process->getErrorOutput()));

            return self::FAILURE;
        }

        file_put_contents($file.'.gz', gzencode((string) file_get_contents($file), 9));
        unlink($file);

        $this->pruneOld($dir, max(1, (int) $this->option('keep')));
        $this->info('Backup written: '.basename($file).'.gz');

        return self::SUCCESS;
    }

    private function pruneOld(string $dir, int $keep): void
    {
        $files = glob($dir.DIRECTORY_SEPARATOR.'*.sql.gz') ?: [];
        rsort($files); // names embed the timestamp, newest first

        foreach (array_slice($files, $keep) as $old) {
            unlink($old);
        }
    }
}
