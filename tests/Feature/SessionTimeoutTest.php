<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_pages_know_the_session_lifetime(): void
    {
        config(['session.lifetime' => 120, 'session.warning' => 5]);

        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('session.lifetime', 120)
            ->where('session.warning', 5)
            ->where('session.remembered', false));
    }

    public function test_with_remember_me_there_is_nothing_to_warn_about(): void
    {
        $this->actingAs(User::factory()->create())
            ->withCookie(Auth::guard('web')->getRecallerName(), 'x')
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('session.remembered', true));
    }

    public function test_signed_out_there_is_no_session_to_watch(): void
    {
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('session', null));
    }

    public function test_keep_alive_extends_only_a_running_session(): void
    {
        // Session gone: a guest, nothing to extend
        $this->getJson(route('session.keep-alive'))->assertUnauthorized();

        $this->actingAs(User::factory()->create())->getJson(route('session.keep-alive'))->assertNoContent();
    }

    public function test_an_expired_session_answers_with_419_on_saving(): void
    {
        // The CSRF check is off in tests: switch it on for this request
        $this->app['env'] = 'local';

        $this->actingAs(User::factory()->create())
            ->post(route('customers.store'), ['_token' => 'token-of-the-expired-session', 'last_name' => 'Muster'])
            ->assertStatus(419);
    }
}
