<?php

namespace App\Models;

use App\Models\Concerns\HasRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * `role` is deliberately NOT mass-assignable, so registration or profile forms
 * can never be used to escalate privileges. Set it explicitly (e.g. `$user->role = ...`).
 *
 * Email verification is on (MustVerifyEmail): new accounts are sent a
 * verification link, and changing the email clears the verification. A
 * verified email is what lets a customer claim guest orders placed with it
 * (FindClaimableGuestOrders). Unverified accounts can still sign in and see
 * orders they placed while signed in.
 *
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $messages_read_at When they last opened Messages on their dashboard.
 * @property Carbon|null $guest_claims_dismissed_at When they said "Not now" to the guest-order claim prompt.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRole, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'messages_read_at' => 'datetime',
            'guest_claims_dismissed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * A "Not now" to the claim prompt was about orders placed with the old
     * email, so changing the email forgets it.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->exists && $user->isDirty('email')) {
                $user->guest_claims_dismissed_at = null;
            }
        });
    }

    /**
     * Orders placed by this user.
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Only staff and admins may enter the Filament back office. Individual
     * resources must still enforce their own policies.
     *
     * @param  Panel  $panel
     * @return bool
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isStaffOrAdmin();
    }
}
