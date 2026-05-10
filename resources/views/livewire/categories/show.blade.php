<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Support\Str;

new #[Layout('layouts.blog')] class extends Component {
    public Category $category;

    public function mount(Category $category): void
    {
        $this->category = $category;
    }

    public function with(): array
    {
        $posts = $this->category->posts()
            ->published()
            ->with('user', 'tags')
            ->latest('published_at')
            ->paginate(12);

        $featured  = null;
        $listItems = $posts->items();

        if ($posts->currentPage() === 1 && count($listItems) > 0) {
            $featured  = $listItems[0];
            $listItems = array_slice($listItems, 1);
        }

        Carbon::setLocale('pt_BR');
        $groupedPosts = collect($listItems)->groupBy(
            fn ($p) => Str::ucfirst($p->published_at->translatedFormat('F Y'))
        );

        return compact('posts', 'featured', 'groupedPosts');
    }
}; ?>

@push('seo_head')
    <title>{{ $category->name }} - {{ config('app.name') }}</title>
    <meta name="description" content="{{ $category->description ?: 'Artigos da categoria ' . $category->name }}">
    <link rel="canonical" href="{{ route('categories.show', $category->slug) }}">
@endpush

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">

    <!-- Hero do Hub -->
    <header class="mb-14">
        <p class="text-sm font-semibold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest mb-3">Categoria</p>
        <h1 class="text-4xl sm:text-5xl font-extrabold text-gray-900 dark:text-white tracking-tight mb-3">{{ $category->name }}</h1>
        @if($category->description)
            <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl leading-relaxed">{{ $category->description }}</p>
        @endif
        <p class="mt-4 text-sm text-gray-400 dark:text-gray-500">
            {{ $posts->total() }} {{ $posts->total() === 1 ? 'artigo publicado' : 'artigos publicados' }}
        </p>
    </header>

    <!-- Post em Destaque (apenas na página 1) -->
    @if($featured)
    <div class="mb-14">
        <p class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-5 flex items-center gap-3">
            <span class="flex-1 h-px bg-gray-100 dark:bg-gray-800"></span>
            Mais recente
            <span class="flex-1 h-px bg-gray-100 dark:bg-gray-800"></span>
        </p>
        <a href="{{ route('posts.show', $featured->slug) }}" wire:navigate
           class="group block rounded-2xl border border-gray-100 dark:border-gray-800 overflow-hidden hover:border-indigo-200 dark:hover:border-indigo-800 hover:shadow-md transition-all">
            @if($featured->cover_image)
                <img src="{{ image_url($featured->cover_image) }}" alt="{{ $featured->title }}"
                     class="w-full max-h-72 object-cover group-hover:opacity-95 transition-opacity">
            @endif
            <div class="p-6">
                <div class="flex flex-wrap gap-2 mb-3">
                    @foreach($featured->tags as $tag)
                        <span class="text-xs bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 px-2 py-0.5 rounded-full">#{{ $tag->name }}</span>
                    @endforeach
                </div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors leading-snug mb-2">
                    {{ $featured->title }}
                </h2>
                <div class="flex items-center gap-3 text-sm text-gray-400">
                    <time>{{ $featured->published_at->format('d/m/Y') }}</time>
                    <span>&bull;</span>
                    <span>{{ $featured->reading_time }} min de leitura</span>
                    <span class="ml-auto text-indigo-500 dark:text-indigo-400 font-semibold group-hover:underline">Ler artigo &rarr;</span>
                </div>
            </div>
        </a>
    </div>
    @endif

    <!-- Lista de Posts por Mês -->
    @if($groupedPosts->isNotEmpty())
    <div class="space-y-14">
        @foreach($groupedPosts as $monthYear => $group)
            <section>
                <h2 class="text-lg font-bold text-gray-500 dark:text-gray-400 mb-6 flex items-center gap-3">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    {{ $monthYear }}
                </h2>
                <div class="space-y-6">
                    @foreach($group as $post)
                        <article class="group flex gap-4 items-start">
                            <!-- Thumb -->
                            <a href="{{ route('posts.show', $post->slug) }}" wire:navigate
                               class="shrink-0 w-20 h-20 rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-800">
                                @if($post->cover_image)
                                    <img src="{{ image_url($post->cover_image) }}" alt=""
                                         class="w-full h-full object-cover group-hover:opacity-85 transition-opacity">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-2xl">📄</div>
                                @endif
                            </a>
                            <div class="flex-1 min-w-0">
                                <a href="{{ route('posts.show', $post->slug) }}" wire:navigate>
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors leading-snug line-clamp-2">
                                        {{ $post->title }}
                                    </h3>
                                </a>
                                <div class="flex items-center gap-2 mt-1.5 text-xs text-gray-400">
                                    <time>{{ $post->published_at->format('d/m/Y') }}</time>
                                    <span>&bull;</span>
                                    <span>{{ $post->reading_time }} min</span>
                                </div>
                                @if($post->tags->isNotEmpty())
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    @foreach($post->tags as $tag)
                                        <a href="{{ route('tags.show', $tag->slug) }}" wire:navigate
                                           class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 px-2 py-0.5 rounded-full hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                            #{{ $tag->name }}
                                        </a>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
    @elseif(!$featured)
        <p class="text-gray-500 dark:text-gray-400 text-center py-12">Nenhum artigo publicado nesta categoria ainda.</p>
    @endif

    @if($posts->hasPages())
        <div class="mt-16">
            {{ $posts->links() }}
        </div>
    @endif
</div>
