<?php

namespace App\Http\Middleware;

use App\Jobs\RecordPageViewJob;
use App\Services\Analytics\SessionTracker;
use App\Support\TrafficClassifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TrackPageView
{
    public function __construct(private readonly SessionTracker $sessions) {}

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
            if (method_exists($response, 'getContent') && method_exists($response, 'setContent')) {
                $response->setContent(str_replace(
                    '__ANALYTICS_VIEW_TOKEN__',
                    $viewToken,
                    (string) $response->getContent(),
                ));
            }

            $ua = $request->userAgent() ?? '';
            $isBot = TrafficClassifier::isBot($ua);
            $device = $this->detectDevice($ua);
            $ipHash = hash('sha256', $request->ip().config('app.key'));
            [$pageReferrer, $pageReferrerDomain] = $this->sessions->referrer($request);
            $sessionId = $isBot ? null : $this->sessions->resolve($request, $device, $ipHash, $ua)->id;

            dispatch(new RecordPageViewJob(
                sessionId: $sessionId,
                path: $request->path(),
                referrer: str_starts_with($pageReferrer ?? '', '/') ? null : $pageReferrerDomain,
                pageReferrer: $pageReferrer,
                pageReferrerDomain: $pageReferrerDomain,
                device: $device,
                ipHash: $ipHash,
                userAgent: $ua ?: null,
                viewToken: $viewToken,
                isBot: $isBot,
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

        if (TrafficClassifier::isPrefetch($request)) {
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
