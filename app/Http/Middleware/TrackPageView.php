<?php

namespace App\Http\Middleware;

use App\Jobs\RecordPageViewJob;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TrackPageView
{
    private const BOT_PATTERNS = [
        'bot', 'spider', 'crawl', 'slurp', 'curl', 'wget', 'python',
        'java', 'ruby', 'go-http', 'httpclient', 'libwww', 'archive',
        'facebookexternalhit', 'ia_archiver', 'whatsapp', 'telegram',
        'linkedinbot', 'twitterbot', 'discordbot', 'slack',
        'headlesschrome', 'lighthouse', 'electron', 'phantomjs',
        'selenium', 'puppeteer', 'playwright',
    ];

    private const IGNORED_PATHS = [
        'login', 'logout', 'register', 'password',
        'wp-admin', 'wp-login', 'xmlrpc', 'wp-includes',
        '_ignition', 'horizon', 'telescope', 'up',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $viewToken = (string) Str::uuid();
        $request->attributes->set('view_token', $viewToken);

        $response = $next($request);

        if ($this->shouldTrack($request) && $response->getStatusCode() === 200) {
            $ua = $request->userAgent() ?? '';

            dispatch(new RecordPageViewJob(
                path:      $request->path(),
                referrer:  $this->extractReferrer($request),
                device:    $this->detectDevice($ua),
                ipHash:    hash('sha256', $request->ip() . config('app.key')),
                userAgent: $ua ?: null,
                viewToken: $viewToken,
                isBot:     $this->isBot($ua),
            ));
        }

        return $response;
    }

    private function shouldTrack(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        if (auth()->check()) {
            return false;
        }

        $path = $request->path();
        foreach (self::IGNORED_PATHS as $ignored) {
            if (str_contains($path, $ignored)) {
                return false;
            }
        }

        return true;
    }

    private function isBot(string $ua): bool
    {
        $ua = strtolower($ua);

        foreach (self::BOT_PATTERNS as $pattern) {
            if (str_contains($ua, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function extractReferrer(Request $request): ?string
    {
        $referrer = $request->headers->get('referer');

        if (! $referrer) {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        if ($host && str_contains($host, parse_url(config('app.url'), PHP_URL_HOST) ?? '')) {
            return null;
        }

        return $host ?: null;
    }

    private function detectDevice(string $ua): string
    {
        $ua = strtolower($ua);

        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) {
            return 'tablet';
        }

        if (str_contains($ua, 'mobile') || str_contains($ua, 'android')) {
            return 'mobile';
        }

        return 'desktop';
    }
}
