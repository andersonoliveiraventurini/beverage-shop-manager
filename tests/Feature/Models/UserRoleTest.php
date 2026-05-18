<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;

it('casts the role column to UserRole enum', function () {
    $user = User::factory()->manager()->create();

    expect($user->fresh()->role)->toBeInstanceOf(UserRole::class)
        ->and($user->fresh()->role)->toBe(UserRole::Manager);
});

it('defaults new users to Attendant', function () {
    $user = User::factory()->create();

    expect($user->fresh()->role)->toBe(UserRole::Attendant);
});

it('exposes role-check helpers on the User model', function () {
    $manager = User::factory()->manager()->create();
    $attendant = User::factory()->attendant()->create();
    $deliverer = User::factory()->deliverer()->create();

    expect($manager->isManager())->toBeTrue()->and($manager->isAttendant())->toBeFalse()->and($manager->isDeliverer())->toBeFalse()
        ->and($attendant->isAttendant())->toBeTrue()->and($attendant->isManager())->toBeFalse()
        ->and($deliverer->isDeliverer())->toBeTrue()->and($deliverer->isManager())->toBeFalse();
});

it('exposes a label for every role', function () {
    expect(UserRole::Manager->label())->toBe('Gerente')
        ->and(UserRole::Attendant->label())->toBe('Atendente')
        ->and(UserRole::Deliverer->label())->toBe('Entregador');
});

it('exposes an options map for select inputs', function () {
    expect(UserRole::options())->toBe([
        'manager' => 'Gerente',
        'attendant' => 'Atendente',
        'deliverer' => 'Entregador',
    ]);
});
