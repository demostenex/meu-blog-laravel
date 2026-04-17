<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Support\Str;

new #[Layout('layouts.blog')] class extends Component {
    public Category $category;

    public function mount(Category $category)
    {
        $this->category = $category;
    }

    public function with(): array
    {
        $posts = $this->category->posts()->published()->with('user')->latest('published_at')->paginate(15);

        Carbon::setLocale('pt_BR');
        $groupedPosts = collect($posts->items())->groupBy(function ($post) {
            return Str::ucfirst($post->published_at->translatedFormat('F Y'));
        });

        return [
            'posts'        => $posts,
            'groupedPosts' => $groupedPosts,
        ];
    }
}; ?>

@push('seo_head')
    <title>{{ $category->name }} - {{ config('app.name') }}</title>
    <meta name="description" content="{{ $category->description ?: 'Artigos da categoria ' . $category->name }}">
    <link rel="canonical" href="{{ route('categories.show', $category->slug) }}">
@endpush

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <header class="mb-16">
        <p class="text-sm font-semibold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest mb-2">Categoria</p>
        <h1 class="text-5xl font-extrabold text-gray-900 dark:text-white tracking-tight mb-2">{{ $category->name }}</h1>
        @if($category->description)
            <p class="text-lg text-gray-600 dark:text-gray-400">{{ $category->description }}</p>
        @endif
        <p class="mt-3 text-sm text-gray-500 dark:text-gray-500">
            {{ $posts->total() }} {{ $posts->total() === 1 ? 'artigo' : 'artigos' }}
        </p>
    </header>

    <div class="space-y-16">
        @forelse($groupedPosts as $monthYear => $group)
            <section>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-8 border-b border-gray-100 dark:border-gray-800 pb-3 flex items-center gap-3">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    {{ $monthYear }}
                </h2>
                <div class="space-y-10">
                    @foreach($group as $post)
                        <article class="group">
                            <div class="flex items-start gap-4">
                                <time class="flex-shrink-0 text-sm text-gray-400 dark:text-gray-500 mt-1 w-16 text-right">
                                    {{ $post->published_at->format('d') }}
                                </time>
                                <div class="flex-1 min-w-0">
                                    <a href="{{ route('posts.show', $post->slug) }}" wire:navigate>
                                        <h3 class="text-xl font-bold text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors leading-snug">{{ $post->title }}</h3>
                                    </a>
                                    <div class="flex flex-wrap gap-2 mt-2">
                                        @foreach($post->tags as $tag)
                                            <a href="{{ route('tags.show', $tag->slug) }}" wire:navigate
                                               class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-2 py-0.5 rounded-full hover:bg-indigo-100 dark:hover:bg-indigo-900 transition-colors">
                                                #{{ $tag->name }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="text-gray-500 dark:text-gray-400">Nenhum artigo publicado nesta categoria ainda.</p>
        @endforelse
    </div>

    @if($posts->hasPages())
        <div class="mt-16">
            {{ $posts->links() }}
        </div>
    @endif
</div>
