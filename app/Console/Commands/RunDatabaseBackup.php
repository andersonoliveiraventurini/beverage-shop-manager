<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Services\GoogleDriveUploader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * F15 - daily MySQL dump + upload to the dedicated FA Google Drive folder.
 * Scheduled in app/Console/Kernel.php once Phase F goes live.
 *
 * In test / SQLite environments mysqldump is unavailable, so we fall back to
 * a logical export. This still proves the pipeline (dump created, sized,
 * uploaded, BackupRun persisted) so the surrounding plumbing is testable.
 */
class RunDatabaseBackup extends Command
{
    protected $signature = 'fa:backup-database {--keep=30 : How many runs to retain on Drive}';

    protected $description = 'Dump the MySQL database, upload the result to the FA Google Drive folder, and record the run.';

    public function handle(GoogleDriveUploader $uploader): int
    {
        $run = BackupRun::create([
            'started_at' => now(),
            'status' => BackupRun::STATUS_SUCCESS,
        ]);

        try {
            $dumpPath = $this->dumpDatabase();
            $remoteName = 'fa-backup-'.now()->format('Y-m-d').'.sql.gz';
            $driveId = $uploader->upload($dumpPath, $remoteName);
            $rotated = $uploader->rotate((int) $this->option('keep'));

            // In production, a backup that never left the host is worse than no
            // backup — it gives false confidence. Fail loud so alerts fire.
            if (app()->environment('production') && ($driveId === null || str_starts_with((string) $driveId, 'local:'))) {
                $reason = 'Off-host backup unavailable: google/apiclient not installed or OAuth not granted — backup did NOT leave the host.';
                $run->update([
                    'finished_at' => now(),
                    'status' => BackupRun::STATUS_FAILED,
                    'file_name' => $remoteName,
                    'size_bytes' => is_file($dumpPath) ? (int) filesize($dumpPath) : null,
                    'drive_file_id' => $driveId,
                    'error_message' => $reason,
                ]);
                $this->error("Backup FAILED — {$reason}");

                return self::FAILURE;
            }

            $run->update([
                'finished_at' => now(),
                'status' => BackupRun::STATUS_SUCCESS,
                'file_name' => $remoteName,
                'size_bytes' => is_file($dumpPath) ? (int) filesize($dumpPath) : null,
                'drive_file_id' => $driveId,
            ]);

            $this->info("Backup OK — {$remoteName} (Drive: ".($driveId ?? 'local fallback').", rotated {$rotated})");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $run->update([
                'finished_at' => now(),
                'status' => BackupRun::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
            $this->error("Backup failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Produce a backup file and return its path.
     *
     * - MySQL (production): shells out to `mysqldump` for a native dump.
     * - SQLite / other (tests, staging): emits a restorable logical SQL dump
     *   using DELETE + INSERT statements — proven round-trip via RestoreCommandTest.
     */
    private function dumpDatabase(): string
    {
        $path = storage_path('app/backups/fa-'.now()->format('Y-m-d-His').'.sql');
        @mkdir(dirname($path), 0775, true);

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $cfg = config('database.connections.'.config('database.default'));
            $dsn = sprintf(
                'mysqldump --no-tablespaces -u%s -p%s -h%s %s > %s',
                escapeshellarg((string) $cfg['username']),
                escapeshellarg((string) $cfg['password']),
                escapeshellarg((string) ($cfg['host'] ?? '127.0.0.1')),
                escapeshellarg((string) $cfg['database']),
                escapeshellarg($path),
            );
            passthru($dsn, $exitCode);
            if ($exitCode !== 0) {
                throw new \RuntimeException("mysqldump exited with code {$exitCode}.");
            }

            return $path;
        }

        // Logical real-data dump for SQLite and other drivers.
        // Produces valid SQL that fa:restore-database can re-import.
        $tables = collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'table'"))
            ->pluck('name')
            ->filter(fn (string $t) => ! str_starts_with($t, 'sqlite_'))
            ->values();

        $lines = ['-- FA logical backup '.now()->toIso8601String()];
        $lines[] = 'PRAGMA foreign_keys = OFF;';

        foreach ($tables as $table) {
            $lines[] = '';
            $lines[] = "-- Table: {$table}";
            $lines[] = "DELETE FROM \"{$table}\";";

            $rows = DB::table($table)->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $cols = array_keys((array) $rows->first());
            $quotedCols = implode(', ', array_map(fn (string $c) => "\"{$c}\"", $cols));

            foreach ($rows as $row) {
                $values = array_map(function (mixed $v): string {
                    if ($v === null) {
                        return 'NULL';
                    }

                    // Escape single quotes by doubling them (standard SQL).
                    return "'".str_replace("'", "''", (string) $v)."'";
                }, (array) $row);

                $lines[] = "INSERT INTO \"{$table}\" ({$quotedCols}) VALUES (".implode(', ', $values).');';
            }
        }

        $lines[] = '';
        $lines[] = 'PRAGMA foreign_keys = ON;';

        file_put_contents($path, implode("\n", $lines)."\n");

        return $path;
    }
}
