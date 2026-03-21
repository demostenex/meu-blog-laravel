<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Post;
use Illuminate\Support\Str;
use Carbon\Carbon;

new #[Layout('layouts.blog')] class extends Component {
    public function with(): array
    {
        $posts = Post::published()->with('user')->latest('published_at')->paginate(15);

        // Agrupa os posts da página atual por Mês e Ano
        Carbon::setLocale('pt_BR');
        $groupedPosts = collect($posts->items())->groupBy(function($post) {
            return Str::ucfirst($post->created_at->translatedFormat('F Y'));
        });

        return [
            'posts' => $posts,
            'groupedPosts' => $groupedPosts,
        ];
    }
}; ?>

@push('seo_head')
    @php $blogOwner = App\Models\User::select('name', 'blog_description')->first(); @endphp
    <title>{{ config('app.name', 'Blog') }}</title>
    <meta name="description" content="{{ $blogOwner?->blog_description ?: config('app.name') . ' - Blog.' }}">
    <link rel="canonical" href="{{ url('/') }}">
@endpush

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <!-- Cabeçalho -->
    <header class="mb-16">
        <h1 class="text-5xl font-extrabold text-gray-900 dark:text-white tracking-tight mb-2">Artigos</h1>
        <p class="text-lg text-gray-600 dark:text-gray-400">
            Explore meus pensamentos, tutoriais e reflexões.
        </p>
    </header>

    <!-- Listagem Agrupada -->
    <div class="space-y-16">
        @forelse($groupedPosts as $monthYear => $group)
            <section>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-8 border-b border-gray-100 dark:border-gray-800 pb-3 flex items-center gap-3">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    {{ $monthYear }}
                </h2>
                
                <div class="flex flex-col gap-8">
                    @foreach($group as $post)
                        <article class="group flex flex-col sm:flex-row gap-6 items-start">
                            <!-- Foto do Artigo -->
                            <a href="{{ route('posts.show', $post->slug) }}" class="shrink-0 w-full sm:w-48 h-32 overflow-hidden rounded-2xl bg-gray-100 dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                                @if($post->cover_image)
                                    <img src="{{ asset('storage/' . $post->cover_image) }}" alt="{{ $post->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-gray-400 dark:text-gray-500 group-hover:scale-105 transition-transform duration-500">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </div>
                                @endif
                            </a>

                            <!-- Dados do Artigo -->
                            <div class="flex-1 flex flex-col justify-center min-h-[8rem]">
                                <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400 mb-3">
                                    <time datetime="{{ $post->created_at->format('Y-m-d') }}" class="font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-2 py-1 rounded-md">{{ $post->created_at->format('d \d\e M') }}</time>
                                    
                                    <!-- Link do Autor -->
                                    <a href="{{ route('author.show', $post->user) }}" class="flex items-center gap-1.5 hover:text-gray-900 dark:hover:text-gray-200 transition-colors">
                                        @if($post->user->profile_photo_path)
                                            <img src="{{ asset('storage/' . $post->user->profile_photo_path) }}" class="w-5 h-5 rounded-full object-cover" alt="{{ $post->user->name }}">
                                        @else
                                            <img src="https://ui-avatars.com/api/?name={{ urlencode($post->user->name) }}&color=7F9CF5&background=EBF4FF" class="w-5 h-5 rounded-full" alt="{{ $post->user->name }}">
                                        @endif
                                        <span>{{ $post->user->name }}</span>
                                    </a>
                                </div>
                                
                                <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors mb-3 leading-tight">
                                    <a href="{{ route('posts.show', $post->slug) }}">{{ $post->title }}</a>
                                </h3>
                                
                                <p class="text-gray-600 dark:text-gray-400 text-sm line-clamp-2 leading-relaxed">
                                    {{ Str::limit(strip_tags($post->content), 200) }}
                                </p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="py-24 text-center bg-gray-50 dark:bg-gray-800/50 rounded-3xl border border-dashed border-gray-200 dark:border-gray-700">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                </svg>
                <h3 class="mt-4 text-sm font-semibold text-gray-900 dark:text-white">Blog vazio</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Nenhum artigo publicado ainda. Volte em breve!</p>
            </div>
        @endforelse
    </div>

    <!-- Paginação -->
    <div class="mt-16">
        {{ $posts->links() }}
    </div>
</div>