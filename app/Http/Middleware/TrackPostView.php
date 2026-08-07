<?php

namespace App\Http\Middleware;

use App\Models\Post;
use App\Support\TrafficClassifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackPostView
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldTrack($request, $response)) {
            return $response;
        }

        $param = $request->route('post');

        // Volt pode entregar o parâmetro como string (slug) antes do SubstituteBindings
        $post = $param instanceof Post
            ? $param
            : ($param ? Post::where('slug', $param)->first() : null);

        if ($post instanceof Post && $post->isPublished()) {
            $post->incrementViews();
        }

        return $response;
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        if (auth()->check()) {
            return false;
        }

        if (TrafficClassifier::isBot($request->userAgent())) {
            return false;
        }

        if (TrafficClassifier::isPrefetch($request)) {
            return false;
        }

        return TrafficClassifier::wantsHtml($request);
    }
}
