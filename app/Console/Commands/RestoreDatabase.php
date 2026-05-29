<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * F15 — restore a previously created SQL dump into the current database.
 *
 * Supports two dump formats:
 *   - Logical SQL (SQLite / non-MySQL): executes the SQL via DB::unprepared()
 *     inside a transaction where the driver supports it.
 *   - MySQL (production): shells out to `mysql` to import the file natively.
 *
 * DESTRUCTIVE — always requires --force in non-interactive mode.
 */
class RestoreDatabase extends Command
{
    protected $signature = 'fa:restore-database
                            {file : Absolute or storage-relative path to a .sql dump}
                            {--force : Skip confirmation prompt (required in non-interactive mode)}';

    protected $description = 'Restore a SQL dump produced by fa:backup-database into the current database.';

    public function handle(): int
    {
        $file = (string) $this->argument('file');

        // Resolve relative paths against storage/app/backups.
        if (! str_starts_with($file, '/') && ! preg_match('/^[A-Za-z]:[\/\\\\]/', $file)) {
            $file = storage_path('app/backups/'.ltrim($file, '/\\'));
        }

        if (! is_file($file)) {
            $this->error("Dump file not found: {$file}");

            return self::FAILURE;
        }

        // Guard destructive operation.
        if (! $this->option('force')) {
            if (! $this->input->isInteractive()) {
                $this->error('Restore aborted: pass --force to run non-interactively.');

                return self::FAILURE;
            }

            if (! $this->confirm("This will OVERWRITE the current database with {$file}. Continue?")) {
                $this->info('Restore cancelled.');

                return self::SUCCESS;
            }
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            return $this->restoreMySQL($file);
        }

        return $this->restoreLogical($file);
    }

    /**
     * Shell out to `mysql` for a native import (production path).
     */
    private function restoreMySQL(string $file): int
    {
        $cfg = config('database.connections.'.config('database.default'));
        $cmd = sprintf(
            'mysql -u%s -p%s -h%s %s < %s',
            escapeshellarg((string) $cfg['username']),
            escapeshellarg((string) $cfg['password']),
            escapeshellarg((string) ($cfg['host'] ?? '127.0.0.1')),
            escapeshellarg((string) $cfg['database']),
            escapeshellarg($file),
        );

        passthru($cmd, $exitCode);

        if ($exitCode !== 0) {
            $this->error("mysql import exited with code {$exitCode}.");

            return self::FAILURE;
        }

        $this->info("Restore complete (mysql): {$file}");

        return self::SUCCESS;
    }

    /**
     * Execute a logical SQL dump via DB::unprepared() inside a transaction
     * where possible (SQLite / non-MySQL).
     */
    private function restoreLogical(string $file): int
    {
        $sql = file_get_contents($file);

        if ($sql === false) {
            $this->error("Could not read dump file: {$file}");

            return self::FAILURE;
        }

        DB::transaction(function () use ($sql): void {
            DB::unprepared($sql);
        });

        $this->info("Restore complete (logical): {$file}");

        return self::SUCCESS;
    }
}
