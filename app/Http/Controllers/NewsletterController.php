<?php

namespace App\Http\Controllers;

use App\Enums\CustomerInterest;
use App\Jobs\SendNewsletterEmail;
use App\Mail\NewsletterMail;
use App\Models\Customer;
use App\Models\Newsletter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Newsletters: one mail to all customers, by interest or to picked customers. Drafts can be
 * changed and tested, sending goes through the queue, one mail per customer.
 */
class NewsletterController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Newsletters/Index', [
            'newsletters' => $request->user()->newsletters()
                ->withCount(['recipients', 'recipients as sent_count' => fn ($query) => $query->whereNotNull('sent_at'),
                    'recipients as failed_count' => fn ($query) => $query->whereNotNull('failed_at')->whereNull('sent_at')])
                ->latest()
                ->get()
                ->map(fn (Newsletter $newsletter) => [
                    ...$newsletter->only(['id', 'subject', 'audience', 'sent_at', 'created_at', 'updated_at', 'recipients_count', 'sent_count', 'failed_count']),
                    'audience_label' => $this->audienceLabel($newsletter),
                ]),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form($request, new Newsletter([
            'subject' => '',
            'body' => '',
            'audience' => $request->filled('customer') ? 'selected' : 'all',
            'interests' => [],
            'customer_ids' => $request->filled('customer') ? [$request->integer('customer')] : [],
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $newsletter = $request->user()->newsletters()->create($this->validated($request));

        return to_route('newsletters.edit', $newsletter)->with('success', 'Entwurf wurde gespeichert.');
    }

    public function edit(Request $request, Newsletter $newsletter): Response
    {
        $this->authorizeOwn($request, $newsletter);

        return $this->form($request, $newsletter);
    }

    public function update(Request $request, Newsletter $newsletter): RedirectResponse
    {
        $this->authorizeOwn($request, $newsletter);
        abort_if($newsletter->isSent(), 403, 'Die Benachrichtigung ist schon verschickt.');

        $newsletter->update($this->validated($request));

        return back()->with('success', 'Entwurf wurde gespeichert.');
    }

    public function destroy(Request $request, Newsletter $newsletter): RedirectResponse
    {
        $this->authorizeOwn($request, $newsletter);

        $newsletter->delete();

        return to_route('newsletters.index')->with('success', "„{$newsletter->subject}“ wurde gelöscht.");
    }

    /**
     * The newsletter to yourself first, to see how it looks.
     */
    public function test(Request $request, Newsletter $newsletter): RedirectResponse
    {
        $this->authorizeOwn($request, $newsletter);

        $to = $request->validate(['to' => 'required|email:rfc|max:255'], [
            'to.required' => 'Bitte gib deine E-Mail-Adresse an.',
        ])['to'];

        $this->ensureCanSend($request);

        Mail::to($to)->queue(new NewsletterMail($newsletter));

        return back()->with('success', "Testmail an {$to} ist unterwegs.");
    }

    /**
     * Fixes the recipients and sends one mail per customer through the queue.
     */
    public function send(Request $request, Newsletter $newsletter): RedirectResponse
    {
        $this->authorizeOwn($request, $newsletter);
        abort_if($newsletter->isSent(), 403, 'Die Benachrichtigung ist schon verschickt.');
        $this->ensureCanSend($request);

        $customers = $newsletter->audienceQuery()->get();

        if ($customers->isEmpty()) {
            return back()->with('error', 'Keine Empfänger: Niemand in der Auswahl hat eine E-Mail-Adresse oder alle haben sich abgemeldet.');
        }

        $recipients = DB::transaction(function () use ($newsletter, $customers) {
            $newsletter->forceFill(['sent_at' => now()])->save();

            return $customers->map(fn (Customer $customer) => $newsletter->recipients()->create([
                'customer_id' => $customer->id,
                'name' => $customer->display_name,
                'email' => $customer->email,
            ]));
        });

        $recipients->each(fn ($recipient) => SendNewsletterEmail::dispatch($recipient));

        return back()->with('success', "„{$newsletter->subject}“ geht an {$recipients->count()} Kunden raus.");
    }

    private function form(Request $request, Newsletter $newsletter): Response
    {
        $user = $request->user();

        return Inertia::render('Newsletters/Edit', [
            'newsletter' => [
                ...$newsletter->only(['id', 'subject', 'body', 'audience', 'interests', 'customer_ids', 'sent_at']),
                'interests' => $newsletter->interests ?? [],
                'customer_ids' => $newsletter->customer_ids ?? [],
            ],
            // Everything to show the recipients live while choosing
            'customers' => $user->customers()->orderBy('last_name')->orderBy('company_name')->get()
                ->map(fn (Customer $customer) => [
                    'id' => $customer->id,
                    'name' => $customer->display_name,
                    'email' => $customer->email,
                    'interests' => $customer->interests ?? [],
                    'opted_out' => $customer->newsletter_opt_out_at !== null,
                ]),
            'interests' => CustomerInterest::options(),
            'audiences' => collect(Newsletter::AUDIENCES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'recipients' => $newsletter->exists && $newsletter->isSent()
                ? $newsletter->recipients()->orderBy('name')->get(['id', 'name', 'email', 'sent_at', 'failed_at', 'error'])
                : [],
            'testTo' => $user->businessProfile?->email ?: ($user->isDemo() ? '' : $user->email),
            'demo' => $user->isDemo(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string|max:20000',
            'audience' => ['required', Rule::in(array_keys(Newsletter::AUDIENCES))],
            'interests' => 'nullable|array',
            'interests.*' => [Rule::enum(CustomerInterest::class)],
            'customer_ids' => 'nullable|array',
            'customer_ids.*' => ['integer', Rule::exists('customers', 'id')->where('user_id', $request->user()->id)],
        ], [
            'subject.required' => 'Bitte gib einen Betreff an.',
            'body.required' => 'Bitte schreib einen Text.',
        ]);

        return [
            ...$validated,
            'interests' => array_values($validated['interests'] ?? []),
            'customer_ids' => array_values(array_map('intval', $validated['customer_ids'] ?? [])),
        ];
    }

    private function audienceLabel(Newsletter $newsletter): string
    {
        return match ($newsletter->audience) {
            'interests' => 'Interesse: '.(collect($newsletter->interests)->map(fn ($interest) => CustomerInterest::tryFrom($interest)?->label())->filter()->implode(', ') ?: '–'),
            'selected' => ($count = count($newsletter->customer_ids ?? [])) === 1 ? '1 ausgewählter Kunde' : "{$count} ausgewählte Kunden",
            default => 'Alle Kunden',
        };
    }

    private function authorizeOwn(Request $request, Newsletter $newsletter): void
    {
        abort_unless($newsletter->user_id === $request->user()->id, 403);
    }

    /**
     * No mails from the public demo; replies need the business email.
     */
    private function ensureCanSend(Request $request): void
    {
        if ($request->user()->isDemo()) {
            throw ValidationException::withMessages(['send' => 'Im Demo-Modus werden keine E-Mails verschickt.']);
        }

        if (blank($request->user()->businessProfile?->email)) {
            throw ValidationException::withMessages(['send' => 'Hinterlege zuerst im Profil deine Firmen-E-Mail – an sie gehen die Antworten der Kunden.']);
        }
    }
}
