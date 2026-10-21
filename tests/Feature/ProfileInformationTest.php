<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\Auth\VerifyEmailQueued as VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProfileInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_information_can_be_updated(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->put('/user/profile-information', [
            'name' => 'Test Name',
            'email' => 'test@example.com',
        ]);

        $this->assertEquals('Test Name', $user->fresh()->name);
        $this->assertEquals('test@example.com', $user->fresh()->email);
    }

    public function test_changing_email_requires_verifying_the_new_address(): void
    {
        Notification::fake();

        $this->actingAs($user = User::factory()->create());

        $this->put('/user/profile-information', [
            'name' => $user->name,
            'email' => 'neu@example.com',
        ]);

        $user->refresh();

        $this->assertSame('neu@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);

        // Until the new address is confirmed, the app redirects to the verification notice
        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    }

    public function test_keeping_the_email_does_not_require_verification(): void
    {
        Notification::fake();

        $this->actingAs($user = User::factory()->create());

        $this->put('/user/profile-information', [
            'name' => 'Neuer Name',
            'email' => $user->email,
        ]);

        $this->assertNotNull($user->fresh()->email_verified_at);
        Notification::assertNothingSent();
    }
}
