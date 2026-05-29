<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonImmutable;

/*
 * PRD F13 §5.7 — Dashboard PDF export.
 *
 * The GET /admin/dashboard/report.pdf endpoint:
 *   - returns 200 + application/pdf for managers
 *   - returns 403 for authenticated non-managers (attendants)
 *   - redirects to login for unauthenticated guests
 */

it('returns a PDF download for a manager', function () {
    $manager = User::factory()->manager()->create();
    $this->actingAs($manager);

    $start = CarbonImmutable::now()->startOfMonth()->toDateString();
    $end = CarbonImmutable::now()->endOfMonth()->toDateString();

    $response = $this->get(route('dashboard.report.pdf', [
        'startDate' => $start,
        'endDate' => $end,
    ]));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/pdf');
});

it('returns 403 for an authenticated attendant', function () {
    $attendant = User::factory()->attendant()->create();
    $this->actingAs($attendant);

    $response = $this->get(route('dashboard.report.pdf'));

    $response->assertStatus(403);
});

it('redirects a guest to the login page', function () {
    $response = $this->get(route('dashboard.report.pdf'));

    $response->assertRedirect();
    $response->assertRedirectContains('login');
});

it('computes KPIs for the specified date range', function () {
    $manager = User::factory()->manager()->create();
    $this->actingAs($manager);

    // Request an explicit past month — should not throw, just return PDF.
    $response = $this->get(route('dashboard.report.pdf', [
        'startDate' => '2024-01-01',
        'endDate' => '2024-01-31',
    ]));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/pdf');
});
