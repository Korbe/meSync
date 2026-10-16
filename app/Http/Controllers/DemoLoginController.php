<?php

namespace App\Http\Controllers;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * One-click login with the demo user from the login page.
 */
class DemoLoginController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = DatabaseSeeder::demoUser();

        // First login: fill the demo account with random data
        if (! $user->customers()->exists()) {
            app(DatabaseSeeder::class)->setContainer(app())->seedFor($user);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('dashboard')->with('success', 'Angemeldet als Demo-Benutzer.');
    }
}
