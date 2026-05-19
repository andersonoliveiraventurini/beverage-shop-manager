<?php

declare(strict_types=1);

use App\Models\BackupRun;
use App\Models\DeliverySetting;
use App\Services\GoogleDriveUploader;

it('records a successful BackupRun when the command runs', function () {
    $this->artisan('fa:backup-database')->assertSuccessful();

    $run = BackupRun::latestRun();
    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(BackupRun::STATUS_SUCCESS)
        ->and($run->file_name)->toContain('fa-backup-')
        ->and($run->size_bytes)->toBeGreaterThan(0);
});

it('skips the Drive upload (drive_file_id stays null) when not authorized yet', function () {
    $this->artisan('fa:backup-database')->assertSuccessful();
    $run = BackupRun::latestRun();

    expect($run->drive_file_id)->toBeNull()
        ->and($run->status)->toBe(BackupRun::STATUS_SUCCESS);
});

it('uses the configured uploader when Drive credentials are present', function () {
    DeliverySetting::current()->update([
        'google_access_token' => 'abc',
        'google_refresh_token' => 'xyz',
        'google_drive_folder_id' => 'folder-id',
    ]);

    $stub = new class extends GoogleDriveUploader {
        public function upload(string $localPath, string $remoteName, string $mime = 'application/gzip'): ?string
        {
            return 'drive:'.$remoteName;
        }
        public function rotate(int $keep = 30): int { return 7; }
    };
    $this->app->instance(GoogleDriveUploader::class, $stub);

    $this->artisan('fa:backup-database')->assertSuccessful();

    expect(BackupRun::latestRun()->drive_file_id)->toContain('drive:');
});
