<?php

declare(strict_types=1);

use App\Models\BackupRun;
use App\Models\Customer;

/**
 * Proves the fa:backup-database → fa:restore-database round-trip.
 *
 * This is the evidence the Board of Advisors (DEC-002) requires:
 *   1. Seed known data.
 *   2. Run fa:backup-database — capture the produced dump path.
 *   3. Mutate / delete some rows so the DB diverges from the dump.
 *   4. Run fa:restore-database <dump> --force.
 *   5. Assert the original rows are back (count + spot-check of values).
 */
it('round-trips the database: backup then restore recovers deleted and mutated rows', function () {
    // ── 1. Seed known rows ────────────────────────────────────────────────────
    $alice = Customer::factory()->create(['name' => 'Alice Backup', 'delivery_fee' => 3.50]);
    $bob = Customer::factory()->create(['name' => 'Bob Backup',   'delivery_fee' => 2.00]);

    $countBefore = Customer::count();
    expect($countBefore)->toBeGreaterThanOrEqual(2);

    // ── 2. Run backup — locate the produced .sql dump ────────────────────────
    $this->artisan('fa:backup-database')->assertSuccessful();

    $run = BackupRun::latestRun();
    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(BackupRun::STATUS_SUCCESS);

    // dumpDatabase() writes a fa-YYYY-MM-DD-His.sql into storage/app/backups.
    // We grab the newest one (RefreshDatabase keeps each test isolated).
    $backupDir = storage_path('app/backups');
    $dumpFiles = glob($backupDir.'/fa-*.sql');
    expect($dumpFiles)->not->toBeEmpty('No .sql dump file found in '.$backupDir);
    usort($dumpFiles, fn ($a, $b) => filemtime($b) - filemtime($a));
    $dumpPath = $dumpFiles[0];

    expect(file_exists($dumpPath))->toBeTrue();

    // Sanity: dump must contain real INSERT data — not just comments.
    $dumpContents = file_get_contents($dumpPath);
    expect($dumpContents)
        ->toContain('Alice Backup')
        ->toContain('Bob Backup')
        ->toContain('INSERT INTO');

    // ── 3. Mutate the DB so it diverges from the dump ────────────────────────
    $alice->update(['name' => 'Alice MUTATED', 'delivery_fee' => 99.99]);
    $bob->delete();
    Customer::factory()->create(['name' => 'Extra Customer']);

    expect(Customer::find($alice->id)->name)->toBe('Alice MUTATED');
    expect(Customer::find($bob->id))->toBeNull();
    expect(Customer::where('name', 'Extra Customer')->exists())->toBeTrue();

    // ── 4. Restore the dump ──────────────────────────────────────────────────
    $this->artisan('fa:restore-database', [
        'file' => $dumpPath,
        '--force' => true,
    ])->assertSuccessful();

    // ── 5. Assert original rows are back ─────────────────────────────────────
    // Row counts match the pre-mutation snapshot.
    expect(Customer::count())->toBe($countBefore);

    // Alice's original values are restored.
    $restoredAlice = Customer::find($alice->id);
    expect($restoredAlice)->not->toBeNull()
        ->and($restoredAlice->name)->toBe('Alice Backup')
        ->and((float) $restoredAlice->delivery_fee)->toBe(3.50);

    // Bob is back.
    $restoredBob = Customer::find($bob->id);
    expect($restoredBob)->not->toBeNull()
        ->and($restoredBob->name)->toBe('Bob Backup');

    // The extra row created after the backup is gone.
    expect(Customer::where('name', 'Extra Customer')->exists())->toBeFalse();
});

it('fails with a non-zero exit code when the dump file does not exist', function () {
    $this->artisan('fa:restore-database', [
        'file' => '/nonexistent/path/dump.sql',
        '--force' => true,
    ])->assertFailed();
});

it('refuses to run without --force when the command is invoked non-interactively', function () {
    // Create a real dump so the file-existence check does not trigger first.
    $this->artisan('fa:backup-database')->assertSuccessful();

    $dumpFiles = glob(storage_path('app/backups').'/fa-*.sql');
    expect($dumpFiles)->not->toBeEmpty();
    usort($dumpFiles, fn ($a, $b) => filemtime($b) - filemtime($a));

    // Run the command in non-interactive mode (--no-interaction) WITHOUT --force.
    // The command must detect this and abort with FAILURE instead of prompting.
    $this->artisan('fa:restore-database '.escapeshellarg($dumpFiles[0]).' --no-interaction')
        ->assertFailed();
});
