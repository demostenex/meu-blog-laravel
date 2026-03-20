<?php

use Livewire\Volt\Component;
use App\Models\Post;

new class extends Component {
    public string $query = '';

    public function with(): array
    {
        $results = [];

        if (strlen($this->query) >= 2) {
            $results = Post::where('title', 'ilike', '%' . $this->query . '%')
                ->orWhere('content', 'ilike', '%' . $this->query . '%')
                ->latest()
                ->take(5)
                ->get();
        }

        return [
            'results' => $results,
        ];
    }
}; ?>

<div class="relative w-full max-w-xs" x-data="{ open: true }" @click.away="open = false">
    <div class="relative">
        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </div>
        <input 
            wire:model.live.debounce.300ms="query" 
            @focus="open = true"
            @input="open = true"
            type="search" 
            class="block w-full p-2 pl-10 text-sm text-gray-900 border border-gray-200 rounded-full bg-gray-50 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:border-gray-700 dark:placeholder-gray-400 dark:text-white transition-all shadow-inner" 
            placeholder="Pesquisar artigos..."
        >
    </div>

    <!-- Dropdown de Resultados -->
    @if(strlen($query) >= 2)
        <div x-show="open" x-transition.opacity class="absolute z-50 w-full mt-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-xl overflow-hidden">
            @if($results->count() > 0)
                <ul class="max-h-64 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($results as $post)
                        <li>
                            <a href="{{ route('posts.show', $post->slug) }}" class="block px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <h4 class="text-sm font-bold text-gray-900 dark:text-white line-clamp-1 mb-1">{{ $post->title }}</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2">
                                    {{ Str::limit(strip_tags($post->content), 80) }}
                                </p>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div class="px-4 py-2 bg-gray-50 dark:bg-gray-800/80 border-t border-gray-100 dark:border-gray-700 text-center text-xs text-gray-500 dark:text-gray-400">
                    Mostrando os {{ $results->count() }} resultados mais recentes.
                </div>
            @else
                <div class="p-4 text-center text-sm text-gray-500 dark:text-gray-400">
                    Nenhum resultado encontrado para "{{ $query }}".
                </div>
            @endif
        </div>
    @endif
</div>