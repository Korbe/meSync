<?php

namespace App\Http\Controllers;

use App\Mail\TestMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Email test center: send a test mail to yourself, directly or through the queue like the
 * real mails, and see the mail setup, the queue and failed mails.
 */
class MailTestController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $mailer = config('mail.default');
        $queue = config('queue.default');
        $database = $queue === 'database';

        $oldestPending = $database ? DB::table('jobs')->min('available_at') : null;

        return Inertia::render('MailTest/Index', [
            'to' => $user->businessProfile?->email ?: ($user->isDemo() ? '' : $user->email),
            'demo' => $user->isDemo(),
            // The setup without secrets (no password, no username)
            'config' => [
                'mailer' => $mailer,
                'host' => config("mail.mailers.{$mailer}.host"),
                'port' => config("mail.mailers.{$mailer}.port"),
                'scheme' => config("mail.mailers.{$mailer}.scheme"),
                'from' => config('mail.from.address'),
                'fromName' => config('mail.from.name'),
                'queue' => $queue,
            ],
            'queue' => [
                'pending' => $database ? DB::table('jobs')->count() : null,
                // Waiting longer than a few minutes: the worker probably isn't running
                'oldestMinutes' => $oldestPending ? (int) floor((time() - $oldestPending) / 60) : null,
                'failed' => DB::table('failed_jobs')->count(),
            ],
            // Only the owner sees the errors, never the public demo
            'failedJobs' => $user->isDemo() ? [] : DB::table('failed_jobs')->latest('failed_at')->limit(5)->get()
                ->map(fn ($job) => [
                    'id' => $job->id,
                    'job' => class_basename(json_decode($job->payload, true)['displayName'] ?? 'Job'),
                    'failed_at' => $job->failed_at,
                    'error' => Str::limit(strtok($job->exception, "\n"), 300),
                ]),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'to' => 'required|email:rfc|max:255',
            'via' => 'required|in:direct,queue',
        ], [
            'to.required' => 'Bitte gib eine E-Mail-Adresse an.',
            'to.email' => 'Bitte gib eine gültige E-Mail-Adresse an.',
        ]);

        // The public demo must not become a way to send mails to any address
        if ($request->user()->isDemo()) {
            return back()->with('error', 'Im Demo-Modus werden keine E-Mails verschickt.');
        }

        $requestedAt = now()->format('d.m.Y H:i:s');

        if ($validated['via'] === 'queue') {
            Mail::to($validated['to'])->queue(new TestMail($request->user(), 'Queue (wie Rechnungen und Angebote)', $requestedAt));

            return back()->with('success', "Testmail an {$validated['to']} liegt in der Queue – sie geht raus, sobald der Worker sie abholt.");
        }

        // Directly: a problem with the mail server shows up right here
        try {
            Mail::to($validated['to'])->send(new TestMail($request->user(), 'Direkt (ohne Queue)', $requestedAt));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Versand fehlgeschlagen: '.Str::limit($e->getMessage(), 300));
        }

        return back()->with('success', "Testmail an {$validated['to']} wurde verschickt – schau in dein Postfach (auch in den Spam-Ordner).");
    }
}
