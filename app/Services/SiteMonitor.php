<?php

namespace App\Services;

use App\Models\MaintenanceContract;
use App\Notifications\SiteDownNotification;
use App\Notifications\SiteRecoveredNotification;
use App\Notifications\SslExpiringNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Checks whether a contract's website is reachable and when its SSL certificate expires.
 */
class SiteMonitor
{
    /** Consecutive failed checks before a site counts as down (avoids alarms on a single hiccup). */
    public const FAILURES_BEFORE_DOWN = 2;

    /** Warn when the certificate expires within this many days (Let's Encrypt renews 30 days ahead). */
    public const SSL_WARNING_DAYS = 14;

    public function check(MaintenanceContract $contract): void
    {
        // The demo account's websites are made up: simulate, never request real domains
        [$up, $responseMs, $error] = $contract->user->isDemo()
            ? [true, random_int(90, 420), null]
            : $this->probe($contract->url);

        $contract->monitor_checked_at = now();
        $contract->monitor_response_ms = $responseMs;

        // History for uptime statistics and the outage list in the monitoring center
        $contract->siteChecks()->create([
            'checked_at' => $contract->monitor_checked_at,
            'up' => $up,
            'response_ms' => $responseMs,
            'error' => $error ? mb_substr($error, 0, 250) : null,
        ]);

        if ($up) {
            $this->markUp($contract);
        } else {
            $this->markFailure($contract, $error);
        }

        if (str_starts_with(strtolower($contract->url), 'https://') && ! $contract->user->isDemo()) {
            $this->checkCertificate($contract);
        }

        $contract->save();
    }

    /**
     * @return array{bool, int|null, string|null}
     */
    protected function probe(string $url): array
    {
        $started = microtime(true);

        try {
            $response = Http::timeout(10)
                ->connectTimeout(10)
                ->withUserAgent('meSync-Monitor/1.0 (+https://korbitsch.at)')
                ->get($url);

            $ms = (int) round((microtime(true) - $started) * 1000);

            return $response->status() < 400
                ? [true, $ms, null]
                : [false, $ms, "HTTP {$response->status()}"];
        } catch (Throwable $e) {
            return [false, null, mb_substr($e->getMessage(), 0, 250)];
        }
    }

    protected function markUp(MaintenanceContract $contract): void
    {
        $wasDown = $contract->monitor_status === 'down';
        $downSince = $contract->monitor_status_since;

        if ($contract->monitor_status !== 'up') {
            $contract->monitor_status = 'up';
            $contract->monitor_status_since = now();
        }

        $contract->monitor_failures = 0;
        $contract->monitor_error = null;

        if ($wasDown) {
            $contract->user->notify(new SiteRecoveredNotification($contract, $downSince ?? now()));
        }
    }

    protected function markFailure(MaintenanceContract $contract, ?string $error): void
    {
        $contract->monitor_failures = min(255, $contract->monitor_failures + 1);
        $contract->monitor_error = $error;

        if ($contract->monitor_status !== 'down' && $contract->monitor_failures >= self::FAILURES_BEFORE_DOWN) {
            $contract->monitor_status = 'down';
            $contract->monitor_status_since = now();

            $contract->user->notify(new SiteDownNotification($contract));
        }
    }

    protected function checkCertificate(MaintenanceContract $contract): void
    {
        $expiresAt = $this->certificateExpiry(parse_url($contract->url, PHP_URL_HOST) ?: '');

        if (! $expiresAt) {
            return;
        }

        $contract->ssl_expires_at = $expiresAt;

        // Warn once per certificate
        $expiresSoon = $expiresAt->lte(now()->addDays(self::SSL_WARNING_DAYS));
        $alreadyWarned = $contract->ssl_warning_sent_for?->isSameDay($expiresAt);

        if ($expiresSoon && ! $alreadyWarned) {
            $contract->ssl_warning_sent_for = $expiresAt->toDateString();
            $contract->user->notify(new SslExpiringNotification($contract));
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
