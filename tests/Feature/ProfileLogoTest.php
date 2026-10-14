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
}
