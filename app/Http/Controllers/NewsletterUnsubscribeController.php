<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The link to unsubscribe at the bottom of every notification mail (signed URL, no login).
 * Opening it only asks: mail scanners open links, they must not unsubscribe anybody.
 * Mail clients' one-click button sends a POST straight away. On the same page the customer
 * can subscribe again, without having to write to anybody.
 */
class NewsletterUnsubscribeController extends Controller
{
    public function show(Customer $customer): Response
    {
        return $this->page($customer, $customer->newsletter_opt_out_at ? 'unsubscribed' : 'ask');
    }

    public function store(Request $request, Customer $customer): Response
    {
        // Back in: the button on the page after unsubscribing
        if ($request->input('action') === 'resubscribe') {
            $customer->forceFill(['newsletter_opt_out_at' => null])->save();

            return $this->page($customer, 'resubscribed');
        }

        // Everything else unsubscribes, also the one-click POST of mail clients
        if (! $customer->newsletter_opt_out_at) {
            $customer->forceFill(['newsletter_opt_out_at' => now()])->save();
        }

        return $this->page($customer, 'unsubscribed');
    }

    private function page(Customer $customer, string $state): Response
    {
        return response()->view('newsletter.unsubscribe', [
            'state' => $state,
            'sender' => $customer->user->businessProfile?->displayName() ?: config('mail.from.name'),
        ]);
    }
}
