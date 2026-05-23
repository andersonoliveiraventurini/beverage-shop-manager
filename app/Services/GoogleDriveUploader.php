<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeliverySetting;
use Illuminate\Support\Facades\Storage;

/**
 * Thin abstraction over the Google Drive API v3 used by F15 (daily backup).
 *
 * The live google/apiclient calls live behind the callDrive() / callRotate()
 * seams. Those seams are GUARDED by class_exists(\Google\Client::class):
 *
 *   - Package NOT installed yet  → fall back to the local filesystem disk, so
 *     the backup pipeline still completes end-to-end (staging, tests).
 *   - Package installed + tokens → real Drive upload + folder bootstrap +
 *     retention rotation.
 *
 * Activation is a deploy step, NOT a code change: run
 * `composer require google/apiclient` on the host and complete the one-time
 * OAuth grant — see docs/RUNBOOK_GOOGLE.md (steps 4–5). Until then this class
 * is inert beyond the local fallback. The real Drive paths are exercised live
 * at deploy (they cannot be unit-tested without OAuth credentials).
 */
class GoogleDriveUploader
{
    /**
     * Upload a local file to the FA Drive backup folder and return the remote
     * file id (or a `local:` id from the fallback). Returns null when Drive is
     * not yet authorized (no access token). The folder itself is bootstrapped
     * on first use, so a missing folder id is fine.
     */
    public function upload(string $localPath, string $remoteName, string $mime = 'application/gzip'): ?string
    {
        $settings = DeliverySetting::current();
        if (! $settings->google_access_token) {
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
     * Override-friendly seam. Real Drive upload when google/apiclient is
     * present; local-filesystem fallback otherwise.
     */
    protected function callDrive(string $localPath, string $remoteName, string $mime, DeliverySetting $settings): ?string
    {
        if (! class_exists(\Google\Client::class)) {
            // Package not installed yet (RUNBOOK_GOOGLE step 5 / composer
            // require). Persist the dump locally so the run still completes.
            Storage::disk('local')->put("backups/{$remoteName}", file_get_contents($localPath));

            return 'local:' . $remoteName;
        }

        $client = $this->makeClient($settings);
        $drive = new \Google\Service\Drive($client);

        $folderId = $settings->google_drive_folder_id ?: $this->ensureFolder($drive, $settings);

        $metadata = new \Google\Service\Drive\DriveFile([
            'name' => $remoteName,
            'parents' => [$folderId],
        ]);

        $created = $drive->files->create($metadata, [
            'data' => file_get_contents($localPath),
            'mimeType' => $mime,
            'uploadType' => 'multipart',
            'fields' => 'id',
        ]);

        return $created->id;
    }

    /**
     * Delete every backup in the folder beyond the most recent $keep.
     */
    protected function callRotate(DeliverySetting $settings, int $keep): int
    {
        if (! class_exists(\Google\Client::class)) {
            return 0;
        }

        $client = $this->makeClient($settings);
        $drive = new \Google\Service\Drive($client);

        $response = $drive->files->listFiles([
            'q' => "'{$settings->google_drive_folder_id}' in parents and trashed = false",
            'orderBy' => 'createdTime desc',
            'fields' => 'files(id, name, createdTime)',
            'pageSize' => 1000,
        ]);

        $stale = array_slice($response->files ?? [], max($keep, 0));
        foreach ($stale as $file) {
            $drive->files->delete($file->id);
        }

        return count($stale);
    }

    /**
     * Build an authorized Google client from the OAuth client JSON + the tokens
     * stored on delivery_settings, refreshing the access token when expired.
     */
    protected function makeClient(DeliverySetting $settings): \Google\Client
    {
        $client = new \Google\Client();
        $client->setAuthConfig(config('services.google.oauth_client_json'));
        $client->setScopes([\Google\Service\Drive::DRIVE_FILE]);
        $client->setAccessType('offline');
        $client->setAccessToken(['access_token' => $settings->google_access_token]);

        $expired = $settings->google_token_expires_at === null
            || $settings->google_token_expires_at->isPast();

        if ($expired && $settings->google_refresh_token) {
            $new = $client->fetchAccessTokenWithRefreshToken($settings->google_refresh_token);

            if (! isset($new['error'])) {
                $settings->update([
                    'google_access_token' => $new['access_token'] ?? $settings->google_access_token,
                    'google_token_expires_at' => isset($new['expires_in'])
                        ? now()->addSeconds((int) $new['expires_in'])
                        : $settings->google_token_expires_at,
                ]);
                $client->setAccessToken(['access_token' => $settings->google_access_token]);
            }
        }

        return $client;
    }

    /**
     * Find the configured backup folder by name, creating it when absent, and
     * persist its id on delivery_settings (RUNBOOK_GOOGLE step 5).
     */
    protected function ensureFolder(\Google\Service\Drive $drive, DeliverySetting $settings): string
    {
        $name = (string) config('services.google.drive_folder_name', 'FA Distribuidora — Backups');
        $escaped = str_replace("'", "\\'", $name);

        $existing = $drive->files->listFiles([
            'q' => "mimeType = 'application/vnd.google-apps.folder' and name = '{$escaped}' and trashed = false",
            'fields' => 'files(id, name)',
            'pageSize' => 1,
        ]);

        $folderId = $existing->files[0]->id ?? null;

        if (! $folderId) {
            $folder = $drive->files->create(
                new \Google\Service\Drive\DriveFile([
                    'name' => $name,
                    'mimeType' => 'application/vnd.google-apps.folder',
                ]),
                ['fields' => 'id'],
            );
            $folderId = $folder->id;
        }

        $settings->update(['google_drive_folder_id' => $folderId]);

        return $folderId;
    }
}
