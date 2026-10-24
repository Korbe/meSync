<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_the_login_form_remembers_the_last_address(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertCookie('last_email', $user->email);

        $this->post('/logout');

        $this->withCookie('last_email', $user->email)->get('/login')
            ->assertInertia(fn (Assert $page) => $page->where('lastEmail', $user->email));
    }

    public function test_the_demo_login_is_not_remembered(): void
    {
        $demo = User::factory()->create(['email' => User::DEMO_EMAIL]);

        $this->post('/login', ['email' => $demo->email, 'password' => 'password'])
            ->assertCookieMissing('last_email');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }
}
