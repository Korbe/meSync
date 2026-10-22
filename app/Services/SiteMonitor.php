<?php

namespace App\Services;

use App\Models\Monitor;
use App\Notifications\SiteDownNotification;
use App\Notifications\SiteRecoveredNotification;
use App\Notifications\SslExpiringNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Checks whether a monitored website is reachable and when its SSL certificate expires.
 */
class SiteMonitor
{
    /** Consecutive failed checks before a site counts as down (avoids alarms on a single hiccup). */
    public const FAILURES_BEFORE_DOWN = 2;

    /** Warn when the certificate expires within this many days (Let's Encrypt renews 30 days ahead). */
    public const SSL_WARNING_DAYS = 14;

    public function check(Monitor $monitor): void
    {
        // The demo account's websites are made up: simulate, never request real domains
        [$up, $responseMs, $error] = $monitor->user->isDemo()
            ? [true, random_int(90, 420), null]
            : $this->probe($monitor);

        $monitor->checked_at = now();
        $monitor->response_ms = $responseMs;

        // History for uptime statistics and the outage list in the monitoring center
        $monitor->siteChecks()->create([
            'checked_at' => $monitor->checked_at,
            'up' => $up,
            'response_ms' => $responseMs,
            'error' => $error ? mb_substr($error, 0, 250) : null,
        ]);

        if ($up) {
            $this->markUp($monitor);
        } else {
            $this->markFailure($monitor, $error);
        }

        if (str_starts_with(strtolower($monitor->url), 'https://') && ! $monitor->user->isDemo()) {
            $this->checkCertificate($monitor);
        }

        $monitor->save();
    }

    /**
     * @return array{bool, int|null, string|null}
     */
    protected function probe(Monitor $monitor): array
    {
        $started = microtime(true);

        try {
            $request = Http::timeout(10)
                ->connectTimeout(10)
                ->withUserAgent('meSync-Monitor/1.0 (+https://korbitsch.at)');

            // Password protected website (htpasswd): log in with the stored credentials
            if ($monitor->auth === 'basic' && $monitor->auth_user) {
                $request = $request->withBasicAuth($monitor->auth_user, (string) $monitor->password());
            }

            $response = $request->get($monitor->url);
            $ms = (int) round((microtime(true) - $started) * 1000);

            // Without credentials the password prompt itself shows the server is up
            if ($response->status() === 401 && $monitor->auth === 'accept_401') {
                return [true, $ms, null];
            }

            return $response->status() < 400
                ? [true, $ms, null]
                : [false, $ms, $response->status() === 401
                    ? 'HTTP 401 – Passwortschutz: Zugangsdaten hinterlegen'
                    : "HTTP {$response->status()}"];
        } catch (Throwable $e) {
            return [false, null, mb_substr($e->getMessage(), 0, 250)];
        }
    }

    protected function markUp(Monitor $monitor): void
    {
        $wasDown = $monitor->status === 'down';
        $downSince = $monitor->status_since;

        if ($monitor->status !== 'up') {
            $monitor->status = 'up';
            $monitor->status_since = now();
        }

        $monitor->failures = 0;
        $monitor->error = null;

        if ($wasDown) {
            $monitor->user->notify(new SiteRecoveredNotification($monitor, $downSince ?? now()));
        }
    }

    protected function markFailure(Monitor $monitor, ?string $error): void
    {
        $monitor->failures = min(255, $monitor->failures + 1);
        $monitor->error = $error;

        if ($monitor->status !== 'down' && $monitor->failures >= self::FAILURES_BEFORE_DOWN) {
            $monitor->status = 'down';
            $monitor->status_since = now();

            $monitor->user->notify(new SiteDownNotification($monitor));
        }
    }

    protected function checkCertificate(Monitor $monitor): void
    {
        $expiresAt = $this->certificateExpiry(parse_url($monitor->url, PHP_URL_HOST) ?: '');

        if (! $expiresAt) {
            return;
        }

        $monitor->ssl_expires_at = $expiresAt;

        // Warn once per certificate
        $expiresSoon = $expiresAt->lte(now()->addDays(self::SSL_WARNING_DAYS));
        $alreadyWarned = $monitor->ssl_warning_sent_for?->isSameDay($expiresAt);

        if ($expiresSoon && ! $alreadyWarned) {
            $monitor->ssl_warning_sent_for = $expiresAt->toDateString();
            $monitor->user->notify(new SslExpiringNotification($monitor));
        }
    }

    protected function certificateExpiry(string $host): ?Carbon
    {
        if ($host === '') {
            return null;
        }

        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ]]);

        $socket = @stream_socket_client("ssl://{$host}:443", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);

        if (! $socket) {
            return null;
        }

        $certificate = stream_context_get_params($socket)['options']['ssl']['peer_certificate'] ?? null;
        fclose($socket);

        $info = $certificate ? openssl_x509_parse($certificate) : false;

        return isset($info['validTo_time_t']) ? Carbon::createFromTimestamp($info['validTo_time_t']) : null;
    }
}
