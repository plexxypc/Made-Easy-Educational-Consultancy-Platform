<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: `->middleware('role:admin,staff')`.
 *
 * Guests are sent to the login page; signed-in users without ANY of the listed
 * roles get a 403 with a clear "not authorized" message.
 */
class EnsureUserHasRole
{
    /**
     * Message shown on the 403 page.
     *
     * @var string
     */
    public const FORBIDDEN_MESSAGE = 'You are not authorized to access this area.';

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure(Request): Response  $next
     * @param  string  ...$roles
     * @return Response
     *
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        abort_unless($user->hasRole(...$roles), Response::HTTP_FORBIDDEN, self::FORBIDDEN_MESSAGE);

        return $next($request);
    }
}
