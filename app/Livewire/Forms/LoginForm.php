<?php

namespace App\Livewire\Forms;

use App\Support\FormThrottle;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

class LoginForm extends Form
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    #[Validate('boolean')]
    public bool $remember = false;

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only(['email', 'password']), $this->remember)) {
            FormThrottle::record_failure('logins', $this->throttleKey());

            throw ValidationException::withMessages([
                'form.email' => trans('auth.failed'),
            ]);
        }

        FormThrottle::clear('logins', $this->throttleKey());
    }

    /**
     * Ensure the authentication request is not rate limited
     * (config('madeeasy.rate_limits.logins'): failed attempts per email + IP).
     *
     * @return void
     *
     * @throws ValidationException
     */
    protected function ensureIsNotRateLimited(): void
    {
        try {
            FormThrottle::ensure_not_limited('logins', $this->throttleKey(), 'form.email');
        } catch (ValidationException $exception) {
            event(new Lockout(request()));

            throw $exception;
        }
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}
