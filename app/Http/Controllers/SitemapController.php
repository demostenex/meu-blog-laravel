<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;

class SitemapController extends Controller
{
    public function index()
    {
        $lastPost = Post::published()->latest('updated_at')->value('updated_at');
        $author   = User::first();

        return response()
            ->view('sitemap.index', compact('lastPost', 'author'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function pages()
    {
        $author = User::first();

        return response()
            ->view('sitemap.pages', compact('author'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function posts()
    {
        $posts = Post::published()
            ->select(['slug', 'updated_at', 'published_at'])
            ->latest('published_at')
            ->get();

        return response()
            ->view('sitemap.posts', compact('posts'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
