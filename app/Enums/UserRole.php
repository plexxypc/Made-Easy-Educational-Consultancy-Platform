<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The three platform roles.
 *
 * Stored as a plain string in `users.role` (see the add_role_to_users_table
 * migration) and cast to this enum on the User model.
 */
enum UserRole: string implements HasColor, HasLabel
{
    case Customer = 'customer';
    case Staff = 'staff';
    case Admin = 'admin';

    /**
     * Human-readable label, used by Filament selects/badges and Blade views.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Staff => 'Staff',
            self::Admin => 'Admin',
        };
    }

    /**
     * Badge colour in Filament.
     *
     * @return string
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Customer => 'gray',
            self::Staff => 'info',
            self::Admin => 'danger',
        };
    }

    /**
     * All role values as strings, e.g. for validation rules.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
