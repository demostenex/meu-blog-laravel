<?php

namespace App\Http\Controllers;

use App\Models\Post;

class FeedController extends Controller
{
    public function __invoke()
    {
        $posts = Post::published()
            ->with('user')
            ->latest('published_at')
            ->limit(20)
            ->get();

        return response()
            ->view('feed', compact('posts'))
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8')
            ->header('Content-Disposition', 'inline');
    }
}
