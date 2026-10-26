<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileLogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_logo_can_be_uploaded(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.logo.store'), ['logo' => UploadedFile::fake()->image('logo.png', 400, 200)])
            ->assertSessionHasNoErrors();

        $media = $user->getFirstMedia('logo');

        $this->assertNotNull($media);
        Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
    }

    public function test_uploading_again_replaces_the_logo(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.logo.store'), ['logo' => UploadedFile::fake()->image('alt.png')]);
        $this->actingAs($user)->post(route('profile.logo.store'), ['logo' => UploadedFile::fake()->image('neu.png')]);

        $this->assertCount(1, $user->fresh()->getMedia('logo'));
        $this->assertSame('neu.png', $user->fresh()->getFirstMedia('logo')->file_name);
    }

    public function test_only_images_are_accepted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.logo.store'), ['logo' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('logo');

        $this->assertNull($user->getFirstMedia('logo'));
    }

    public function test_logo_can_be_removed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('profile.logo.store'), ['logo' => UploadedFile::fake()->image('logo.png')]);

        $this->actingAs($user)->delete(route('profile.logo.destroy'))->assertSessionHas('success');

        $this->assertNull($user->fresh()->getFirstMedia('logo'));
    }

    public function test_profile_page_receives_logo_url(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('profile.logo.store'), ['logo' => UploadedFile::fake()->image('logo.png')]);

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertInertia(fn (Assert $page) => $page->where('logoUrl', fn ($url) => str_contains($url, 'logo.png')));
    }

    public function test_dark_mode_logo_is_a_separate_upload(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('profile.logo.store'), ['logo' => UploadedFile::fake()->image('logo.png')]);

        $this->actingAs($user)
            ->post(route('profile.logo_dark.store'), ['logo_dark' => UploadedFile::fake()->image('logo-white.png', 400, 200)])
            ->assertSessionHasNoErrors();

        $this->assertSame('logo-white.png', $user->getFirstMedia('logo_dark')->file_name);
        $this->assertSame('logo.png', $user->getFirstMedia('logo')->file_name);   // untouched
        $this->actingAs($user)->get(route('profile.show'))
            ->assertInertia(fn (Assert $page) => $page->where('logoDarkUrl', fn ($url) => str_contains($url, 'logo-white.png')));

        $this->actingAs($user)->delete(route('profile.logo_dark.destroy'));
        $this->assertNull($user->fresh()->getFirstMedia('logo_dark'));
        $this->assertNotNull($user->fresh()->getFirstMedia('logo'));
    }

    public function test_mails_switch_to_the_dark_mode_logo(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('profile.logo.store'), ['logo' => UploadedFile::fake()->image('logo.png', 800, 200)]);
        $this->actingAs($user)->post(route('profile.logo_dark.store'), ['logo_dark' => UploadedFile::fake()->image('logo-white.png', 800, 200)]);

        $mail = new \App\Mail\TestMail($user->fresh(), 'Direkt', '26.10.2026 10:00:00');
        $html = $mail->render();

        // Both versions, the dark one hidden until Outlook darkens the mail; classic Outlook a flat one
        $this->assertStringContainsString('class="logo-light"', $html);
        $this->assertStringContainsString('class="logo-dark"', $html);
        $this->assertStringContainsString('[data-ogsc] .logo-dark', $html);
        $this->assertStringContainsString('<!--[if mso]>', $html);
        $this->assertStringContainsString('width="200" height="50"', $html);   // scaled to the header box
    }
}
