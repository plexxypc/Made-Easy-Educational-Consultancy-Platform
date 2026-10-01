<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Livewire\Actions\Logout;
use Illuminate\Http\RedirectResponse;

class LogoutController extends Controller
{
    /**
     * Sign out from a plain form (the public header has no Livewire), then go home.
     *
     * @param  Logout  $logout
     * @return RedirectResponse
     */
    public function __invoke(Logout $logout): RedirectResponse
    {
        $logout();

        return redirect()->route('home');
    }
}
