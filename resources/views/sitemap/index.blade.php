<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

    <sitemap>
        <loc>{{ url('/sitemap-pages.xml') }}</loc>
        <lastmod>{{ now()->toAtomString() }}</lastmod>
    </sitemap>

    <sitemap>
        <loc>{{ url('/sitemap-posts.xml') }}</loc>
        @if($lastPost)
        <lastmod>{{ $lastPost->toAtomString() }}</lastmod>
        @else
        <lastmod>{{ now()->toAtomString() }}</lastmod>
        @endif
    </sitemap>

</sitemapindex>
