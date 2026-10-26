<?php

namespace Tests\Feature;

use App\Jobs\SendNewsletterEmail;
use App\Mail\NewsletterMail;
use App\Models\Customer;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->businessProfile()->create(['name' => 'Lukas Korbitsch', 'email' => 'office@example.test']);
        $this->actingAs($this->user);
    }

    private function customer(array $attributes = []): Customer
    {
        return Customer::factory()->for($this->user)->create(['email' => fake()->unique()->safeEmail(), 'interests' => [], ...$attributes]);
    }

    private function newsletter(array $attributes = []): Newsletter
    {
        return $this->user->newsletters()->create(['subject' => 'Neu bei mir', 'body' => "Hallo!\nZweite Zeile", 'audience' => 'all', ...$attributes]);
    }

    public function test_the_audience_follows_the_choice_and_leaves_out_unsubscribed_and_without_email(): void
    {
        $pcHelp = $this->customer(['interests' => ['pc_help']]);
        $website = $this->customer(['interests' => ['website', 'pc_build']]);
        $unsubscribed = $this->customer(['interests' => ['pc_help'], 'newsletter_opt_out_at' => now()]);
        $this->customer(['email' => null, 'interests' => ['pc_help']]);
        Customer::factory()->for(User::factory()->create())->create(['email' => 'fremd@example.test']);

        $ids = fn (Newsletter $newsletter) => $newsletter->audienceQuery()->pluck('id')->sort()->values()->all();

        $this->assertSame([$pcHelp->id, $website->id], $ids($this->newsletter()));
        $this->assertSame([$pcHelp->id], $ids($this->newsletter(['audience' => 'interests', 'interests' => ['pc_help']])));
        $this->assertSame([$website->id], $ids($this->newsletter(['audience' => 'interests', 'interests' => ['website']])));
        $this->assertSame([], $ids($this->newsletter(['audience' => 'interests', 'interests' => []])));
        $this->assertSame([$website->id], $ids($this->newsletter(['audience' => 'selected', 'customer_ids' => [$website->id, $unsubscribed->id]])));
    }

    public function test_a_draft_is_saved_and_sent_once_to_each_customer(): void
    {
        Queue::fake();
        $first = $this->customer();
        $second = $this->customer();

        $this->post(route('newsletters.store'), ['subject' => 'Urlaub', 'body' => 'Bin weg.', 'audience' => 'all'])->assertSessionHasNoErrors();
        $newsletter = $this->user->newsletters()->first();

        $this->get(route('newsletters.edit', $newsletter))->assertInertia(fn (Assert $page) => $page->component('Newsletters/Edit')->has('customers', 2));

        $this->post(route('newsletters.send', $newsletter))->assertSessionHas('success');
        $this->assertNotNull($newsletter->fresh()->sent_at);
        $this->assertEqualsCanonicalizing([$first->email, $second->email], $newsletter->recipients()->pluck('email')->all());
        Queue::assertPushed(SendNewsletterEmail::class, 2);

        // Sent: stays as it went out
        $this->post(route('newsletters.send', $newsletter))->assertForbidden();
        $this->put(route('newsletters.update', $newsletter), ['subject' => 'X', 'body' => 'Y', 'audience' => 'all'])->assertForbidden();
    }

    public function test_each_mail_is_personal_with_a_link_to_unsubscribe(): void
    {
        Mail::fake();
        $customer = $this->customer(['title_prefix' => null, 'first_name' => 'Max', 'last_name' => 'Muster', 'title_suffix' => null, 'company_name' => null]);
        $newsletter = $this->newsletter();
        $this->post(route('newsletters.send', $newsletter));

        // The queue runs synchronously in tests
        Mail::assertSent(NewsletterMail::class, function (NewsletterMail $mail) use ($customer) {
            $html = $mail->render();

            return $mail->hasTo($customer->email)
                && str_contains($html, 'Guten Tag Max Muster')
                && str_contains($html, 'Hier abmelden')
                && str_contains($mail->headers()->text['List-Unsubscribe'], '/newsletter/unsubscribe/');
        });
        $this->assertNotNull($newsletter->recipients()->first()->sent_at);
    }

    public function test_customers_unsubscribe_with_the_signed_link_only(): void
    {
        $customer = $this->customer();
        $url = URL::signedRoute('newsletter.unsubscribe', $customer);
        auth()->logout();

        // Opening the link only asks (mail scanners open links)
        $this->get($url)->assertOk()->assertSee('Ja, abmelden');
        $this->assertNull($customer->fresh()->newsletter_opt_out_at);

        // Without CSRF token: also the one-click button of the mail client
        $this->post($url)->assertOk()->assertSee('Abgemeldet');
        $this->assertNotNull($customer->fresh()->newsletter_opt_out_at);

        // Changed their mind: back in on the same page, without writing to anybody
        $this->get($url)->assertSee('Doch wieder anmelden');
        $this->post($url, ['action' => 'resubscribe'])->assertOk()->assertSee('Wieder angemeldet');
        $this->assertNull($customer->fresh()->newsletter_opt_out_at);

        // Guessing other customers doesn't work
        $this->post(route('newsletter.unsubscribe.store', $this->customer()))->assertForbidden();
    }

    public function test_the_customer_form_shows_and_undoes_the_unsubscribe(): void
    {
        $customer = $this->customer(['newsletter_opt_out_at' => now(), 'last_name' => 'Muster']);
        $data = ['last_name' => 'Muster', 'email' => $customer->email, 'interests' => ['pc_help']];

        $this->put(route('customers.update', $customer), $data)->assertSessionHasNoErrors();
        $this->assertNotNull($customer->fresh()->newsletter_opt_out_at);   // untouched without the checkbox

        $this->put(route('customers.update', $customer), [...$data, 'newsletter' => true]);
        $this->assertNull($customer->fresh()->newsletter_opt_out_at);

        $this->put(route('customers.update', $customer), [...$data, 'newsletter' => false]);
        $this->assertNotNull($customer->fresh()->newsletter_opt_out_at);
    }

    public function test_the_customer_list_filters_by_notification_status(): void
    {
        $allowed = $this->customer();
        $unsubscribed = $this->customer(['newsletter_opt_out_at' => now()]);
        $this->customer(['email' => null]);

        $this->get(route('customers.index', ['notify' => 'yes']))->assertInertia(fn (Assert $page) => $page
            ->where('notify', 'yes')->has('customers.data', 1)->where('customers.data.0.id', $allowed->id));
        $this->get(route('customers.index', ['notify' => 'no']))->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 1)->where('customers.data.0.id', $unsubscribed->id));
        $this->get(route('customers.index'))->assertInertia(fn (Assert $page) => $page->has('customers.data', 3));
    }

    public function test_test_mail_goes_to_yourself_and_the_demo_sends_nothing(): void
    {
        Mail::fake();
        $newsletter = $this->newsletter();

        $this->post(route('newsletters.test', $newsletter), ['to' => 'ich@example.test'])->assertSessionHas('success');
        Mail::assertQueued(NewsletterMail::class, fn (NewsletterMail $mail) => $mail->hasTo('ich@example.test') && $mail->customer === null);

        $demo = User::factory()->create(['email' => User::DEMO_EMAIL]);
        $demoNewsletter = $demo->newsletters()->create(['subject' => 'X', 'body' => 'Y', 'audience' => 'all']);
        Customer::factory()->for($demo)->create(['email' => 'irgendwer@example.test']);

        $this->actingAs($demo)->post(route('newsletters.send', $demoNewsletter))->assertSessionHasErrors('send');
        $this->assertNull($demoNewsletter->fresh()->sent_at);
    }

    public function test_newsletters_of_others_are_forbidden(): void
    {
        $newsletter = $this->newsletter();

        $this->actingAs(User::factory()->create());
        $this->get(route('newsletters.edit', $newsletter))->assertForbidden();
        $this->post(route('newsletters.send', $newsletter))->assertForbidden();
        $this->post(route('newsletters.store'), ['subject' => 'X', 'body' => 'Y', 'audience' => 'selected', 'customer_ids' => [$this->customer()->id]])
            ->assertSessionHasErrors('customer_ids.0');
    }
}
