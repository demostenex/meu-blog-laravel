<?php

namespace App\Support;

use Illuminate\Http\Request;

class TrafficClassifier
{
    private const BOT_PATTERNS = [
        'bot', 'spider', 'crawl', 'slurp', 'curl', 'wget', 'python',
        'java', 'ruby', 'go-http', 'httpclient', 'libwww', 'archive',
        'facebookexternalhit', 'ia_archiver', 'whatsapp', 'telegram',
        'linkedinbot', 'twitterbot', 'discordbot', 'slack',
        'headlesschrome', 'lighthouse', 'electron', 'phantomjs',
        'selenium', 'puppeteer', 'playwright', 'guzzlehttp', 'axios',
        'node-fetch', 'undici', 'okhttp', 'postmanruntime', 'insomnia',
    ];

    public static function isBot(?string $userAgent): bool
    {
        $ua = strtolower(trim($userAgent ?? ''));

        if ($ua === '') {
            return true;
        }

        foreach (self::BOT_PATTERNS as $pattern) {
            if (str_contains($ua, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public static function isPrefetch(Request $request): bool
    {
        $headers = strtolower(implode(' ', array_filter([
            $request->headers->get('Purpose'),
            $request->headers->get('Sec-Purpose'),
            $request->headers->get('X-Moz'),
        ])));

        return str_contains($headers, 'prefetch')
            || str_contains($headers, 'prerender');
    }

    public static function wantsHtml(Request $request): bool
    {
        $accept = strtolower($request->headers->get('Accept', ''));

        return $accept === ''
            || str_contains($accept, 'text/html')
            || str_contains($accept, '*/*');
    }
}
