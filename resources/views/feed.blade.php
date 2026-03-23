<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">
    <channel>
        <title>{{ config('app.name') }}</title>
        <link>{{ url('/') }}</link>
        <description>Blog pessoal de tecnologia, código e cultura digital.</description>
        <language>pt-BR</language>
        <lastBuildDate>{{ $posts->first()?->published_at?->toRfc822String() }}</lastBuildDate>
        <atom:link href="{{ route('feed') }}" rel="self" type="application/rss+xml"/>
        @foreach($posts as $post)
        <item>
            <title><![CDATA[{{ $post->title }}]]></title>
            <link>{{ route('posts.show', $post->slug) }}</link>
            <guid isPermaLink="true">{{ route('posts.show', $post->slug) }}</guid>
            <pubDate>{{ $post->published_at->toRfc822String() }}</pubDate>
            <description><![CDATA[{{ Str::limit(strip_tags($post->content), 300) }}]]></description>
            <content:encoded><![CDATA[{!! $post->content !!}]]></content:encoded>
            @if($post->cover_image)
            <enclosure url="{{ asset('storage/' . $post->cover_image) }}" type="image/jpeg"/>
            @endif
        </item>
        @endforeach
    </channel>
</rss>
