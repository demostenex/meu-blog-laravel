<?php

namespace App\Observers;

use App\Models\Post;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\ResponseCache\Facades\ResponseCache;

class PostObserver
{
    /**
     * Pinga o Google sempre que um post passa de rascunho para publicado.
     */
    public function updated(Post $post): void
    {
        ResponseCache::clear();

        $wasPublished  = $post->getOriginal('published_at') !== null;
        $nowPublished  = $post->published_at !== null;
        $justPublished = $post->wasChanged('published_at') && ! $wasPublished && $nowPublished;

        if (! $justPublished) {
            return;
        }

        $sitemapUrl = url('/sitemap.xml');

        try {
            Http::timeout(5)->get('https://www.google.com/ping', ['sitemap' => $sitemapUrl]);
            Log::info("[Sitemap] Google pingado com sucesso para: {$sitemapUrl}");
        } catch (\Throwable $e) {
            Log::warning("[Sitemap] Falha ao pingar o Google: {$e->getMessage()}");
        }
    }

    public function deleted(Post $post): void
    {
        ResponseCache::clear();
    }
}
