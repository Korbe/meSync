<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * "Reset your password", sent through the queue like every other mail.
 */
class ResetPasswordQueued extends ResetPassword implements ShouldQueue
{
    use Queueable;
}
