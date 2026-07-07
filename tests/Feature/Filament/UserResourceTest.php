<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->manager = User::factory()->manager()->create();
    $this->actingAs($this->manager);
});

it('renders the Users list page', function () {
    livewireTest(ListUsers::class)->assertSuccessful();
});

it('creates a user through the Filament form', function () {
    livewireTest(CreateUser::class)
        ->fillForm([
            'name' => 'Nova Atendente',
            'email' => 'atendente@fa.test',
            'role' => UserRole::Attendant->value,
            'password' => 'senha-segura',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'atendente@fa.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Nova Atendente')
        ->and($user->role)->toBe(UserRole::Attendant)
        ->and(Hash::check('senha-segura', $user->password))->toBeTrue();
});

it('rejects duplicate e-mails and short passwords', function () {
    livewireTest(CreateUser::class)
        ->fillForm([
            'name' => 'Duplicada',
            'email' => $this->manager->email,
            'role' => UserRole::Attendant->value,
            'password' => 'curta',
        ])
        ->call('create')
        ->assertHasFormErrors(['email', 'password']);
});

it('edits a user and keeps the password when the field is left blank', function () {
    $user = User::factory()->attendant()->create();
    $originalHash = $user->password;

    livewireTest(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['name' => 'Nome Atualizado'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh())
        ->name->toBe('Nome Atualizado')
        ->password->toBe($originalHash);
});

it('ignores role changes on the authenticated manager\'s own account', function () {
    livewireTest(EditUser::class, ['record' => $this->manager->getRouteKey()])
        ->fillForm(['role' => UserRole::Deliverer->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->manager->fresh()->role)->toBe(UserRole::Manager);
});

it('forbids a manager from deleting their own account', function () {
    expect($this->manager->can('delete', $this->manager))->toBeFalse()
        ->and($this->manager->can('delete', User::factory()->deliverer()->create()))->toBeTrue();
});

it('exposes the Filament resource navigation metadata', function () {
    expect(UserResource::getNavigationLabel())->toBe('Usuários')
        ->and(UserResource::getNavigationGroup())->toBe('Sistema')
        ->and(UserResource::getNavigationSort())->toBe(97);
});
