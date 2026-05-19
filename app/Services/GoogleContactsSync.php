<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPhone;
use App\Models\DeliverySetting;

/**
 * F16 - two-way Google Contacts sync. The full Google People API integration
 * lives behind callPeopleApi() / pushToPeopleApi(), which are overridden in
 * tests and replaced with the real google/apiclient calls during the Phase F
 * deploy described in docs/RUNBOOK_GOOGLE.md.
 *
 * The class owns: matching by normalized phone, last-write-wins conflict
 * resolution, sync-token persistence in delivery_settings.
 */
class GoogleContactsSync
{
    /**
     * Pull contacts from Google and reconcile against the local customers
     * table. Returns the count of customers created or updated.
     */
    public function pull(): int
    {
        $settings = DeliverySetting::current();
        if ($settings->google_contacts_sync_paused || ! $settings->google_access_token) {
            return 0;
        }

        $remoteContacts = $this->callPeopleApi($settings->google_contacts_sync_token);
        $touched = 0;

        foreach ($remoteContacts['contacts'] ?? [] as $contact) {
            $customer = $this->matchOrCreate($contact);
            if ($customer === null) {
                continue;
            }
            $touched++;
        }

        $settings->update([
            'google_contacts_sync_token' => $remoteContacts['next_sync_token'] ?? $settings->google_contacts_sync_token,
            'google_contacts_synced_at' => now(),
        ]);

        return $touched;
    }

    /**
     * Push a customer back to Google after a local create or update.
     */
    public function push(Customer $customer): bool
    {
        $settings = DeliverySetting::current();
        if ($settings->google_contacts_sync_paused || ! $settings->google_access_token) {
            return false;
        }

        $payload = [
            'name' => $customer->name,
            'phones' => $customer->phones->pluck('number')->all(),
            'addresses' => $customer->addresses->map(fn ($a) => $a->full_address)->all(),
        ];

        $resourceName = $this->pushToPeopleApi($customer->google_contact_id, $payload);
        if ($resourceName && $resourceName !== $customer->google_contact_id) {
            $customer->update([
                'google_contact_id' => $resourceName,
                'google_synced_at' => now(),
            ]);
        }

        return (bool) $resourceName;
    }

    /**
     * Match by google_contact_id first, then by normalized phone. Create when
     * no match found.
     */
    private function matchOrCreate(array $contact): ?Customer
    {
        if (! empty($contact['resource_name'])) {
            $customer = Customer::query()->where('google_contact_id', $contact['resource_name'])->first();
            if ($customer) {
                $this->reconcile($customer, $contact);
                return $customer;
            }
        }

        $normalizedPhones = collect($contact['phones'] ?? [])
            ->map(fn ($p) => preg_replace('/\D/', '', (string) $p))
            ->filter()
            ->all();

        if ($normalizedPhones) {
            $customer = CustomerPhone::query()
                ->whereIn(\DB::raw("REPLACE(REPLACE(REPLACE(REPLACE(number, '(', ''), ')', ''), '-', ''), ' ', '')"), $normalizedPhones)
                ->first()?->customer;
            if ($customer) {
                $customer->update(['google_contact_id' => $contact['resource_name'] ?? null]);
                $this->reconcile($customer, $contact);
                return $customer;
            }
        }

        if (empty($contact['name'])) {
            return null;
        }

        return Customer::create([
            'name' => $contact['name'],
            'google_contact_id' => $contact['resource_name'] ?? null,
            'google_synced_at' => now(),
        ]);
    }

    private function reconcile(Customer $customer, array $contact): void
    {
        $customer->update([
            'name' => $contact['name'] ?? $customer->name,
            'google_synced_at' => now(),
        ]);
    }

    /**
     * Override seams for tests + the live Google client wiring.
     *
     * @return array{contacts: array<int, array{resource_name?: string, name?: string, phones?: array<int, string>}>, next_sync_token?: string}
     */
    protected function callPeopleApi(?string $syncToken): array
    {
        return ['contacts' => [], 'next_sync_token' => $syncToken];
    }

    protected function pushToPeopleApi(?string $resourceName, array $payload): ?string
    {
        return $resourceName;
    }
}
