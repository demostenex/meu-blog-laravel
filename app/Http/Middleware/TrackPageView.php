<?php

namespace App\Http\Middleware;

use App\Jobs\RecordPageViewJob;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackPageView
{
    private const BOT_PATTERNS = [
        'bot', 'spider', 'crawl', 'slurp', 'curl', 'wget', 'python',
        'java', 'ruby', 'go-http', 'httpclient', 'libwww', 'archive',
        'facebookexternalhit', 'ia_archiver', 'whatsapp', 'telegram',
        'linkedinbot', 'twitterbot', 'discordbot', 'slack',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldTrack($request)) {
            dispatch(new RecordPageViewJob(
                path:     $request->path(),
                referrer: $this->extractReferrer($request),
                device:   $this->detectDevice($request->userAgent() ?? ''),
                ipHash:   hash('sha256', $request->ip() . config('app.key')),
            ));
        }

        return $next($request);
    }

    private function shouldTrack(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        if (auth()->check()) {
            return false;
        }

        $ua = strtolower($request->userAgent() ?? '');

        foreach (self::BOT_PATTERNS as $pattern) {
            if (str_contains($ua, $pattern)) {
                return false;
            }
        }

        return true;
    }

    private function extractReferrer(Request $request): ?string
    {
        $referrer = $request->headers->get('referer');

        if (! $referrer) {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        // Ignora auto-referências do próprio domínio
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
