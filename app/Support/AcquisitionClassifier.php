<?php

namespace App\Support;

class AcquisitionClassifier
{
    public static function source(?string $utmSource, ?string $referrerDomain): string
    {
        if ($utmSource !== null && trim($utmSource) !== '') {
            return self::normalize($utmSource);
        }

        if ($referrerDomain === null) {
            return 'direct';
        }

        $domain = strtolower($referrerDomain);

        return match (true) {
            str_contains($domain, 'google.') => 'google',
            str_contains($domain, 'facebook.com'), str_contains($domain, 'fb.com') => 'facebook',
            str_contains($domain, 'instagram.com') => 'instagram',
            str_contains($domain, 'youtube.com'), str_contains($domain, 'youtu.be') => 'youtube',
            str_contains($domain, 'bing.com') => 'bing',
            default => self::normalize($domain),
        };
    }

    private static function normalize(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9._-]+/', '_', $normalized) ?? '';

        return substr(trim($normalized, '_'), 0, 100) ?: 'direct';
    }
}
