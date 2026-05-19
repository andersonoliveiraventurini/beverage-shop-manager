# Runbook — Google Integrations (Phase F)

> **Owner**: Anderson de Oliveira Venturini
> **Last updated**: 2026-05-19
> **Status**: Code substrate landed (commit history `feat(google): F15+F16 substrate`); the live OAuth grant + production wiring described below remain a manual step the system owner performs once.

This runbook walks the dedicated Google account through the one-time OAuth grant that unlocks F15 (Drive backup) and F16 (Contacts sync). After the grant, the scheduled commands run unattended.

The code paths exercise this substrate via mock-friendly seams:

- [`app/Services/GoogleDriveUploader.php`](../app/Services/GoogleDriveUploader.php) wraps the Drive v3 API.
- [`app/Services/GoogleContactsSync.php`](../app/Services/GoogleContactsSync.php) wraps the People API.
- [`app/Console/Commands/RunDatabaseBackup.php`](../app/Console/Commands/RunDatabaseBackup.php) — scheduled daily at 03:00 BRT.
- DB columns on `delivery_settings`: `google_access_token`, `google_refresh_token`, `google_token_expires_at`, `google_drive_folder_id`, `google_contacts_sync_token`, `google_contacts_synced_at`, `google_contacts_sync_paused`.

---

## Prerequisites

1. **Dedicated FA Google account** (`fa.distribuidora.sistema@gmail.com` or similar). Created and verified.
2. **Manager email** on file for failure alerts.
3. **Gmail SMTP app password** generated for the same account.

## Step 1 — Google Cloud project

1. Sign in to <https://console.cloud.google.com/> using the dedicated FA account.
2. Create a new project — name it **"FA Distribuidora — Sistema"**.
3. Enable APIs (APIs & Services → Library):
   - **Google Drive API**
   - **People API**
4. OAuth consent screen → External (since the depot's account is a regular Gmail). Add the same FA account as a test user. Scopes:
   - `https://www.googleapis.com/auth/drive.file`
   - `https://www.googleapis.com/auth/contacts`

## Step 2 — Credentials

1. Credentials → Create credentials → **OAuth client ID** → Web application.
2. Authorized redirect URI: `https://fa.andersonventurini.cloud/admin/google/oauth/callback`.
3. Download the JSON. Save it on the server at `/var/www/fa/storage/app/google-oauth-client.json` (gitignored).

## Step 3 — Environment variables

Append to the production `.env`:

```
GOOGLE_OAUTH_CLIENT_JSON=/var/www/fa/storage/app/google-oauth-client.json
GOOGLE_DRIVE_FOLDER_NAME="FA Distribuidora — Backups"
GOOGLE_CONTACTS_GROUP="FA Distribuidora"
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=fa.distribuidora.sistema@gmail.com
MAIL_PASSWORD=<app password>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=fa.distribuidora.sistema@gmail.com
MAIL_FROM_NAME="FA Distribuidora"
```

## Step 4 — One-time OAuth grant

1. Sign in to the panel as the manager.
2. Settings → Integrações Google → "Conectar Google Drive".
3. Browser redirects to Google, you accept the scopes, and Google returns the auth code.
4. Server exchanges the code for `access_token` + `refresh_token` and stores them on `delivery_settings`.

The same Settings page exposes "Conectar Google Contacts" — same OAuth flow but adds the contacts scope.

## Step 5 — Drive folder bootstrap

The first `fa:backup-database` run does:

1. Calls `drive.files.list` looking for the configured folder name.
2. If absent, creates it via `drive.files.create` and persists the folder id in `delivery_settings.google_drive_folder_id`.

## Step 6 — Schedule the jobs

Append to `routes/console.php` (or `app/Console/Kernel.php` in older Laravel layouts):

```php
use Illuminate\Support\Facades\Schedule;
use App\Services\GoogleContactsSync;

Schedule::command('fa:backup-database --keep=30')
    ->dailyAt('03:00')
    ->timezone('America/Sao_Paulo');

Schedule::call(fn () => app(GoogleContactsSync::class)->pull())
    ->everyFifteenMinutes();
```

Enable the system cron once on the host:

```cron
* * * * * cd /var/www/fa && php artisan schedule:run >> /dev/null 2>&1
```

## Step 7 — Verify

- Manually trigger a backup: `php artisan fa:backup-database --keep=30`. Confirm a `BackupRun` row + a `fa-backup-YYYY-MM-DD.sql.gz` in the Drive folder.
- Manually trigger a contacts pull: `php artisan tinker --execute='app(\App\Services\GoogleContactsSync::class)->pull();'`. Confirm new customers materialize.

## Failure modes + responses

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| Backup runs are `status=failed` with `error_message` containing `mysqldump: command not found` | The PHP container is missing `mysql-client` | Add `RUN apt-get install -y default-mysql-client` to the Dockerfile and redeploy |
| Drive uploads work locally but stop on staging | Refresh token expired (a manual revocation in the Google account) | Re-run Step 4. Tokens are stored encrypted at rest |
| Contacts pull returns 0 forever | `google_contacts_sync_paused = true` | Settings → Integrações Google → "Retomar sincronização" |
| Quota exceeded (Drive) | Daily request quota — extremely rare for 1 backup/day | None required |

## Pause without uninstalling

The Settings page exposes a toggle "Pausar sincronização" that flips `google_contacts_sync_paused = true`. The backup job is independent and continues to run.

---

## Code map for future maintainers

```text
docs/RUNBOOK_GOOGLE.md            # this file
config/services.php               # google client_id / client_secret bindings (added when the live integration ships)
app/Services/GoogleDriveUploader.php  # Drive v3 wrapper
app/Services/GoogleContactsSync.php   # People API wrapper
app/Console/Commands/RunDatabaseBackup.php  # fa:backup-database
app/Models/BackupRun.php          # one row per dump attempt
database/migrations/2026_05_19_105614_add_google_oauth_state_to_delivery_settings_table.php
database/migrations/2026_05_19_105625_create_backup_runs_table.php
tests/Feature/Services/BackupCommandTest.php
tests/Feature/Services/GoogleContactsSyncTest.php
```

Override the `protected function callDrive(...)` / `callPeopleApi(...)` seams to swap in the real `google/apiclient` client when wiring production.
