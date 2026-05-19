<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\DeliverySetting;
use App\Services\GoogleContactsSync;

beforeEach(function () {
    DeliverySetting::current()->update([
        'google_access_token' => 'fake',
        'google_refresh_token' => 'fake',
    ]);
});

it('skips the pull when sync is paused', function () {
    DeliverySetting::current()->update(['google_contacts_sync_paused' => true]);

    $sync = new class extends GoogleContactsSync {
        protected function callPeopleApi(?string $syncToken): array
        {
            throw new \RuntimeException('API must not be hit when paused');
        }
    };

    expect($sync->pull())->toBe(0);
});

it('creates local customers from new remote contacts', function () {
    $sync = new class extends GoogleContactsSync {
        protected function callPeopleApi(?string $syncToken): array
        {
            return [
                'contacts' => [
                    ['resource_name' => 'people/r1', 'name' => 'Maria Silva', 'phones' => ['(19) 99999-1111']],
                    ['resource_name' => 'people/r2', 'name' => 'João Souza', 'phones' => ['(19) 99999-2222']],
                ],
                'next_sync_token' => 'token-after-pull',
            ];
        }
    };

    expect($sync->pull())->toBe(2)
        ->and(Customer::where('google_contact_id', 'people/r1')->first()?->name)->toBe('Maria Silva')
        ->and(DeliverySetting::current()->google_contacts_sync_token)->toBe('token-after-pull')
        ->and(DeliverySetting::current()->google_contacts_synced_at)->not->toBeNull();
});

it('updates an existing customer when google_contact_id matches', function () {
    $existing = Customer::factory()->create(['google_contact_id' => 'people/r1', 'name' => 'Maria Antiga']);

    $sync = new class extends GoogleContactsSync {
        protected function callPeopleApi(?string $syncToken): array
        {
            return [
                'contacts' => [
                    ['resource_name' => 'people/r1', 'name' => 'Maria Atualizada', 'phones' => []],
                ],
            ];
        }
    };

    $sync->pull();

    expect($existing->fresh()->name)->toBe('Maria Atualizada');
});

it('pushes a customer back to Google when sync is active', function () {
    $customer = Customer::factory()->create(['name' => 'Cliente Novo']);

    $sync = new class extends GoogleContactsSync {
        public array $pushed = [];
        protected function pushToPeopleApi(?string $resourceName, array $payload): ?string
        {
            $this->pushed[] = $payload;
            return $resourceName ?: 'people/new-id';
        }
    };

    expect($sync->push($customer))->toBeTrue()
        ->and($customer->fresh()->google_contact_id)->toBe('people/new-id')
        ->and($sync->pushed[0]['name'])->toBe('Cliente Novo');
});
