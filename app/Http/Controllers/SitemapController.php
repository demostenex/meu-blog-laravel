<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;

class SitemapController extends Controller
{
    public function __invoke()
    {
        $posts = Post::published()
            ->select(['slug', 'updated_at', 'published_at'])
            ->latest('published_at')
            ->get();

        $author = User::first();

        return response()
            ->view('sitemap', compact('posts', 'author'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
