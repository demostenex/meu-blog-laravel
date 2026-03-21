<?php

use Livewire\Volt\Component;
use App\Models\Post;

new class extends Component {
    public int $totalPosts = 0;
    public int $totalViews = 0;
    public $topPosts;

    public function mount(): void
    {
        $this->totalPosts = Post::where('user_id', auth()->id())->count();
        $this->totalViews = Post::where('user_id', auth()->id())->sum('views_count');
        $this->topPosts   = Post::where('user_id', auth()->id())
            ->orderByDesc('views_count')
            ->limit(5)
            ->get(['id', 'title', 'slug', 'views_count', 'created_at']);
    }
}; ?>

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            <!-- Cards de Estatísticas -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-2xl shrink-0">📝</div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total de Posts</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $totalPosts }}</p>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center text-2xl shrink-0">👁️</div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total de Visualizações</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($totalViews, 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>

            <!-- Posts Mais Visualizados -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">🏆 Posts Mais Visualizados</h3>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($topPosts as $post)
                        <div class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('posts.show', $post->slug) }}" target="_blank"
                                   class="text-sm font-medium text-gray-900 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400 truncate block">
                                    {{ $post->title }}
                                </a>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $post->created_at->format('d/m/Y') }}</p>
                            </div>
                            <div class="ml-4 shrink-0 flex items-center gap-1.5 text-sm font-semibold text-green-600 dark:text-green-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                {{ number_format($post->views_count, 0, ',', '.') }}
                            </div>
                        </div>
                    @empty
                        <p class="px-6 py-8 text-sm text-gray-400 text-center italic">Nenhum post ainda. <a href="{{ route('posts.create') }}" class="text-blue-500 hover:underline">Crie o primeiro!</a></p>
                    @endforelse
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
