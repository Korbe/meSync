<?php

namespace Tests\Feature;

use App\Http\Middleware\ProtectDemoProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoProfileTest extends TestCase
{
    use RefreshDatabase;

    private function demo(): User
    {
        return User::factory()->create(['name' => 'Demo', 'email' => User::DEMO_EMAIL]);
    }

    public function test_demo_user_cannot_change_profile_information_or_password(): void
    {
        $demo = $this->demo();

        $this->actingAs($demo)->put(route('user-profile-information.update'), ['name' => 'Hacker', 'email' => 'x@example.com'])
            ->assertSessionHas('error', ProtectDemoProfile::MESSAGE);
        $this->actingAs($demo)->put(route('user-password.update'), ['current_password' => 'password', 'password' => 'neues-passwort', 'password_confirmation' => 'neues-passwort']);

        $demo->refresh();
        $this->assertSame('Demo', $demo->name);
        $this->assertSame(User::DEMO_EMAIL, $demo->email);
        $this->assertTrue(Hash::check('password', $demo->password));
    }

    public function test_demo_user_cannot_change_company_data_logo_or_delete_the_account(): void
    {
        Storage::fake('public');
        $demo = $this->demo();
        $demo->businessProfile()->create(['name' => 'Demo GmbH']);

        $this->actingAs($demo)->put(route('business-profile.update'), ['name' => 'Anders']);
        $this->actingAs($demo)->post(route('profile.logo.store'), ['logo' => UploadedFile::fake()->image('logo.png')]);
        $this->actingAs($demo)->post(route('two-factor.enable'));
        $this->actingAs($demo)->delete(route('current-user.destroy'), ['password' => 'password']);

        $this->assertSame('Demo GmbH', $demo->businessProfile()->first()->name);
        $this->assertNull($demo->fresh()->getFirstMedia('logo'));
        $this->assertNull($demo->fresh()->two_factor_secret);
        $this->assertNotNull(User::find($demo->id));
    }

    public function test_json_requests_get_a_403(): void
    {
        $this->actingAs($this->demo())->putJson(route('user-profile-information.update'), ['name' => 'X'])->assertForbidden();
    }

    public function test_demo_user_can_still_work_with_business_data(): void
    {
        $this->actingAs($this->demo())->post(route('customers.store'), [
            'first_name' => 'Maria', 'last_name' => 'Huber', 'country' => 'Österreich',
        ])->assertSessionHasNoErrors();
    }

    public function test_normal_users_can_change_their_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('user-profile-information.update'), ['name' => 'Neu', 'email' => $user->email]);

        $this->assertSame('Neu', $user->fresh()->name);
    }
}
