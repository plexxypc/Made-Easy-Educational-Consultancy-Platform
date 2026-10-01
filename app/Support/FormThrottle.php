<?php

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Rate limits from config('madeeasy.rate_limits'), for both kinds of form.
 *
 * Route forms use the named limiters built from route_limit(). Livewire
 * forms (sign-in, registration, password reset, ...) all post to the same
 * /livewire/update URL, so route middleware can't tell them apart; they call
 * hit() from the action instead, which shows the limit as a field error.
 */
final class FormThrottle
{
    /**
     * [attempts, per minutes] for a named limit.
     *
     * @param  string  $name  A key of config('madeeasy.rate_limits').
     * @return array{0: int, 1: int}
     *
     * @throws InvalidArgumentException If the limit isn't configured.
     */
    public static function limit(string $name): array
    {
        $limit = config("madeeasy.rate_limits.{$name}");

        if (! is_array($limit) || count($limit) !== 2) {
            throw new InvalidArgumentException("No rate limit configured for [{$name}] in config/madeeasy.php.");
        }

        return [(int) $limit[0], (int) $limit[1]];
    }

    /**
     * The limit as a route limiter Limit (the caller adds ->by()).
     *
     * @param  string  $name
     * @return Limit
     */
    public static function route_limit(string $name): Limit
    {
        [$attempts, $minutes] = self::limit($name);

        return Limit::perMinutes($minutes, $attempts);
    }

    /**
     * Count one attempt, or refuse it with a validation error on $field when
     * the limit is already used up.
     *
     * @param  string  $name  Limit name.
     * @param  string  $key  Who is limited (IP, user id, email + IP).
     * @param  string  $field  Field the error is shown on.
     * @return void
     *
     * @throws ValidationException When over the limit.
     */
    public static function hit(string $name, string $key, string $field): void
    {
        self::ensure_not_limited($name, $key, $field);

        RateLimiter::hit(self::cache_key($name, $key), self::limit($name)[1] * 60);
    }

    /**
     * Refuse with a validation error on $field when the limit is used up,
     * without counting this attempt (for limits that only count failures).
     *
     * @param  string  $name
     * @param  string  $key
     * @param  string  $field
     * @return void
     *
     * @throws ValidationException When over the limit.
     */
    public static function ensure_not_limited(string $name, string $key, string $field): void
    {
        $cache_key = self::cache_key($name, $key);

        if (! RateLimiter::tooManyAttempts($cache_key, self::limit($name)[0])) {
            return;
        }

        $seconds = RateLimiter::availableIn($cache_key);

        throw ValidationException::withMessages([
            $field => "Too many attempts. Please try again in {$seconds} seconds.",
        ]);
    }

    /**
     * Count a failed attempt (pairs with ensure_not_limited()).
     *
     * @param  string  $name
     * @param  string  $key
     * @return void
     */
    public static function record_failure(string $name, string $key): void
    {
        RateLimiter::hit(self::cache_key($name, $key), self::limit($name)[1] * 60);
    }

    /**
     * Forget the attempts (e.g. after a successful sign-in).
     *
     * @param  string  $name
     * @param  string  $key
     * @return void
     */
    public static function clear(string $name, string $key): void
    {
        RateLimiter::clear(self::cache_key($name, $key));
    }

    /**
     * @param  string  $name
     * @param  string  $key
     * @return string
     */
    private static function cache_key(string $name, string $key): string
    {
        return 'form-throttle:'.$name.':'.$key;
    }
}
