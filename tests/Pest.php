<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

if (! function_exists('livewireTest')) {
    function livewireTest(string $class, array $params = [])
    {
        return Livewire::test($class, $params);
    }
}
