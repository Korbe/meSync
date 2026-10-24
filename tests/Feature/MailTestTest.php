<?php

namespace Tests\Feature;

use App\Mail\TestMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MailTestTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_shows_the_setup_without_secrets(): void
    {
        config(['mail.mailers.smtp.password' => 'geheim']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('mail-test.index'))->assertInertia(fn (Assert $page) => $page
            ->component('MailTest/Index')
            ->where('to', $user->email)
            ->has('config.mailer')
            ->has('queue.failed'));

        $this->assertStringNotContainsString('geheim', $response->getContent());
    }

    public function test_a_test_mail_goes_out_directly_or_through_the_queue(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('mail-test.send'), ['to' => 'ich@example.test', 'via' => 'direct'])->assertSessionHas('success');
        Mail::assertSent(TestMail::class, fn (TestMail $mail) => $mail->hasTo('ich@example.test'));

        $this->post(route('mail-test.send'), ['to' => 'ich@example.test', 'via' => 'queue'])->assertSessionHas('success');
        Mail::assertQueued(TestMail::class);

        $this->post(route('mail-test.send'), ['to' => 'kein-mail', 'via' => 'direct'])->assertSessionHasErrors('to');
    }

    public function test_a_failing_mail_server_is_reported(): void
    {
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('Connection refused'));

        $this->actingAs(User::factory()->create())->post(route('mail-test.send'), ['to' => 'ich@example.test', 'via' => 'direct'])
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'Connection refused'));
    }

    public function test_the_demo_sends_nothing(): void
    {
        Mail::fake();
        $demo = User::factory()->create(['email' => User::DEMO_EMAIL]);

        $this->actingAs($demo)->post(route('mail-test.send'), ['to' => 'irgendwer@example.test', 'via' => 'direct'])->assertSessionHas('error');
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_the_test_mail_renders(): void
    {
        $html = (new TestMail(User::factory()->create(), 'Direkt (ohne Queue)', '24.10.2026 10:00:00'))->render();

        $this->assertStringContainsString('Testmail', $html);
        // Only Korbitsch or the own logo, no app branding
        $this->assertStringNotContainsString('meSync', $html);
        $this->assertStringNotContainsString('mesync.png', $html);
    }
}
