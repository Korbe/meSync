<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * "Please confirm your email address", sent through the queue like every other mail.
 */
class VerifyEmailQueued extends VerifyEmail implements ShouldQueue
{
    use Queueable;
}
