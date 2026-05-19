<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeliverySetting;
use Illuminate\Support\Facades\Storage;

/**
 * Thin abstraction over the Google Drive API v3 used by F15. The full
 * google/apiclient integration is wired during deploy (see
 * docs/RUNBOOK_GOOGLE.md); this class exposes the methods the rest of the
 * codebase calls so the backup job + tests can be developed against an
 * interface that is trivial to fake.
 *
 * Hooks the binary to upload through the local filesystem disk by default
 * so even staging without OAuth can exercise the full flow.
 */
class GoogleDriveUploader
{
    /**
     * Upload a local file to the configured FA Drive folder and return the
     * remote file id. Returns null when Drive is not yet authorized.
     */
    public function upload(string $localPath, string $remoteName, string $mime = 'application/gzip'): ?string
    {
        $settings = DeliverySetting::current();
        if (! $settings->google_access_token || ! $settings->google_drive_folder_id) {
            return null;
        }

        return $this->callDrive($localPath, $remoteName, $mime, $settings);
    }

    /**
     * Rotate the FA Drive folder, keeping only the most recent $keep entries.
     * No-op when not authorized.
     *
     * @return int Files deleted in the rotation.
     */
    public function rotate(int $keep = 30): int
    {
        $settings = DeliverySetting::current();
        if (! $settings->google_access_token || ! $settings->google_drive_folder_id) {
            return 0;
        }

        return $this->callRotate($settings, $keep);
    }

    /**
     * Override-friendly seam — overridden in tests / replaced once the live
     * Google client is wired in.
     */
    protected function callDrive(string $localPath, string $remoteName, string $mime, DeliverySetting $settings): ?string
    {
        // Fallback: persist the dump to the local filesystem so the backup
        // process still runs end-to-end on machines without OAuth.
        Storage::disk('local')->put("backups/{$remoteName}", file_get_contents($localPath));
        return 'local:' . $remoteName;
    }

    protected function callRotate(DeliverySetting $settings, int $keep): int
    {
        return 0; // Real rotation hits Drive API; placeholder for staging tests.
    }
}
