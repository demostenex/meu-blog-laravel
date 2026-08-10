<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsSession;
use App\Support\AcquisitionClassifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class SessionTracker
{
    public const VISITOR_COOKIE = 'analytics_visitor_id';

    public const SESSION_COOKIE = 'analytics_session_id';

    public const INACTIVITY_MINUTES = 30;

    public function resolve(Request $request, string $device, string $ipHash, string $userAgent): AnalyticsSession
    {
        $visitorId = $this->validUuid($request->cookie(self::VISITOR_COOKIE)) ?? (string) Str::uuid();
        $sessionId = $this->validUuid($request->cookie(self::SESSION_COOKIE));

        $session = $sessionId === null ? null : AnalyticsSession::find($sessionId);

        if ($session && $session->visitor_id === $visitorId && $session->last_seen_at->gte(now()->subMinutes(self::INACTIVITY_MINUTES))) {
            $session->update(['last_seen_at' => now()]);
        } else {
            $session = $this->create($request, $visitorId, $device, $ipHash, $userAgent);
        }

        Cookie::queue(cookie(self::VISITOR_COOKIE, $visitorId, 60 * 24 * 365, secure: $request->isSecure(), httpOnly: true, sameSite: 'lax'));
        Cookie::queue(cookie(self::SESSION_COOKIE, $session->id, 60 * 24 * 365, secure: $request->isSecure(), httpOnly: true, sameSite: 'lax'));

        return $session;
    }

    private function create(Request $request, string $visitorId, string $device, string $ipHash, string $userAgent): AnalyticsSession
    {
        [$referrer, $referrerDomain] = $this->referrer($request);
        $utm = collect(['source', 'medium', 'campaign', 'content', 'term'])
            ->mapWithKeys(fn (string $key) => ["utm_{$key}" => $this->limited($request->query("utm_{$key}"), 255)])
            ->all();

        return AnalyticsSession::create([
            'id' => (string) Str::uuid(),
            'visitor_id' => $visitorId,
            'started_at' => now(),
            'last_seen_at' => now(),
            'landing_path' => $request->path(),
            'landing_url' => $this->landingUrl($request),
            'initial_referrer' => $referrer,
            'initial_referrer_domain' => $referrerDomain,
            'source_key' => AcquisitionClassifier::source(
                $utm['utm_source'],
                str_starts_with($referrer ?? '', '/') ? null : $referrerDomain,
            ),
            ...$utm,
            'device' => $device,
            'browser' => $this->browser($userAgent),
            'operating_system' => $this->operatingSystem($userAgent),
            'ip_hash' => $ipHash,
            'country' => $this->country($request),
            'is_bot' => false,
        ]);
    }

    public function referrer(Request $request): array
    {
        $value = $request->headers->get('referer');
        if (! is_string($value) || ! in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return [null, null];
        }

        $host = strtolower((string) parse_url($value, PHP_URL_HOST));
        if ($host === '') {
            return [null, null];
        }

        $appHost = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));
        $path = '/'.ltrim((string) parse_url($value, PHP_URL_PATH), '/');

        return $host === $appHost
            ? [substr($path, 0, 1000), $host]
            : [substr(parse_url($value, PHP_URL_SCHEME).'://'.$host.$path, 0, 1000), $host];
    }

    private function landingUrl(Request $request): string
    {
        $query = collect(['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'])
            ->mapWithKeys(fn (string $key) => [$key => $this->limited($request->query($key), 255)])
            ->filter(fn ($value) => $value !== null)
            ->all();

        return substr($request->url().($query === [] ? '' : '?'.http_build_query($query)), 0, 1000);
    }

    private function limited(mixed $value, int $length): ?string
    {
        return is_string($value) && trim($value) !== '' ? Str::limit(trim($value), $length, '') : null;
    }

    private function validUuid(mixed $value): ?string
    {
        return is_string($value) && Str::isUuid($value) ? $value : null;
    }

    private function browser(string $ua): ?string
    {
        return match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => null,
        };
    }

    private function operatingSystem(string $ua): ?string
    {
        return match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone'), str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => null,
        };
    }

    private function country(Request $request): ?string
    {
        $country = strtoupper((string) ($request->headers->get('CF-IPCountry') ?? ''));

        return preg_match('/^[A-Z]{2}$/', $country) ? $country : null;
    }
}
