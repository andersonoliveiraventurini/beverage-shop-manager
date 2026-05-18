<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Manager = 'manager';
    case Attendant = 'attendant';
    case Deliverer = 'deliverer';

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Gerente',
            self::Attendant => 'Atendente',
            self::Deliverer => 'Entregador',
        };
    }

    /**
     * Manager has unrestricted access; everyone else sees only their day-to-day
     * surfaces. Used by Filament policies and `canAccess` checks across resources.
     */
    public function isManager(): bool
    {
        return $this === self::Manager;
    }

    public function isAttendant(): bool
    {
        return $this === self::Attendant;
    }

    public function isDeliverer(): bool
    {
        return $this === self::Deliverer;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role) => [$role->value => $role->label()])
            ->all();
    }
}
