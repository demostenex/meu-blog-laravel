<?php

namespace App\Http\Middleware;

use App\Models\Post;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackPostView
{
    public function handle(Request $request, Closure $next): Response
    {
        $param = $request->route('post');

        // Volt pode entregar o parâmetro como string (slug) antes do SubstituteBindings
        $post = $param instanceof Post
            ? $param
            : ($param ? Post::where('slug', $param)->first() : null);

        if ($post instanceof Post && $post->isPublished()) {
            $isAuthor = auth()->check() && auth()->id() === $post->user_id;

            if (! $isAuthor) {
                $post->incrementViews();
            }
        }

        return $next($request);
    }
}
