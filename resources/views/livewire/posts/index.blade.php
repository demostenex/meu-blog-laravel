<?php

use Livewire\Volt\Component;
use App\Models\Post;
use App\Models\Category;
use Carbon\Carbon;

new class extends Component {
    public string $search = '';
    public string $filterMonth = '';  // formato: YYYY-MM
    public string $filterCategory = '';
    public string $filterStatus = '';

    public function with(): array
    {
        $query = auth()->user()->posts()
            ->with('category', 'tags')
            ->when($this->search, fn($q) => $q->where('title', 'ilike', '%' . $this->search . '%'))
            ->when($this->filterMonth, function ($q) {
                [$year, $month] = explode('-', $this->filterMonth);
                $q->whereYear('created_at', $year)->whereMonth('created_at', $month);
            })
            ->when($this->filterCategory, fn($q) => $q->where('category_id', $this->filterCategory))
            ->when($this->filterStatus === 'published', fn($q) => $q->whereNotNull('published_at'))
            ->when($this->filterStatus === 'draft', fn($q) => $q->whereNull('published_at'))
            ->latest();

        Carbon::setLocale('pt_BR');
        $months = auth()->user()->posts()
            ->selectRaw("TO_CHAR(created_at, 'YYYY-MM') as month_key, TO_CHAR(created_at, 'Mon YYYY') as label")
            ->orderByRaw("month_key DESC")
            ->distinct()
            ->pluck('label', 'month_key');

        return [
            'posts'      => $query->paginate(15),
            'categories' => Category::orderBy('name')->get(),
            'months'     => $months,
        ];
    }

    public function deletePost(Post $post)
    {
        $this->authorize('delete', $post);
        $post->delete();
    }

    public function publishPost(Post $post)
    {
        abort_if($post->user_id !== auth()->id(), 403);
        $post->update(['published_at' => now()]);
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'filterMonth', 'filterCategory', 'filterStatus');
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Meus Artigos') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">

                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Seus artigos</h3>
                    <a href="{{ route('posts.create') }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        Novo Artigo
                    </a>
                </div>

                <!-- Filtros -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
                    <div>
                        <input wire:model.live.debounce.300ms="search" type="text"
                            placeholder="Buscar por título..."
                            class="w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <select wire:model.live="filterMonth"
                            class="w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todos os meses</option>
                            @foreach($months as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <select wire:model.live="filterCategory"
                            class="w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todas as categorias</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <select wire:model.live="filterStatus"
                            class="flex-1 text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todos os status</option>
                            <option value="published">Publicados</option>
                            <option value="draft">Rascunhos</option>
                        </select>
                        @if($search || $filterMonth || $filterCategory || $filterStatus)
                            <button wire:click="resetFilters" type="button"
                                class="px-3 py-1.5 text-xs text-gray-500 dark:text-gray-400 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                title="Limpar filtros">✕</button>
                        @endif
                    </div>
                </div>

                @if($posts->isEmpty())
                    <p class="text-gray-500 dark:text-gray-400 py-8 text-center">
                        @if($search || $filterMonth || $filterCategory || $filterStatus)
                            Nenhum artigo encontrado com esses filtros.
                        @else
                            Você ainda não escreveu nenhum artigo. Comece agora mesmo!
                        @endif
                    </p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="px-6 py-3">Título</th>
                                    <th scope="col" class="px-6 py-3">Categoria / Tags</th>
                                    <th scope="col" class="px-6 py-3">Status</th>
                                    <th scope="col" class="px-6 py-3">Visualizações</th>
                                    <th scope="col" class="px-6 py-3">Data</th>
                                    <th scope="col" class="px-6 py-3 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($posts as $post)
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700" wire:key="{{ $post->id }}">
                                        <th scope="row" class="px-6 py-4 font-medium text-gray-900 dark:text-white max-w-xs">
                                            <span class="line-clamp-2">{{ $post->title }}</span>
                                            @if($post->content_en_status === 'done')
                                                <span class="ml-1" title="Tradução em inglês disponível">🇺🇸</span>
                                            @endif
                                        </th>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col gap-1">
                                                @if($post->category)
                                                    <span class="inline-flex w-fit items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300">
                                                        {{ $post->category->name }}
                                                    </span>
                                                @endif
                                                @if($post->tags->isNotEmpty())
                                                    <div class="flex flex-wrap gap-1">
                                                        @foreach($post->tags as $tag)
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                                                #{{ $tag->name }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                                @if(!$post->category && $post->tags->isEmpty())
                                                    <span class="text-gray-400 dark:text-gray-600 text-xs">—</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            @if($post->isPublished())
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-400">✅ Publicado</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-400">📝 Rascunho</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center gap-1 text-gray-500 dark:text-gray-400">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                {{ number_format($post->views_count) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            {{ ($post->published_at ?? $post->created_at)->format('d/m/Y') }}
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="inline-flex flex-col items-end gap-1">
                                                @if(!$post->isPublished())
                                                    <button wire:click="publishPost({{ $post->id }})" class="font-medium text-green-600 dark:text-green-500 hover:underline">Publicar</button>
                                                @endif
                                                <a href="{{ route('posts.show', $post->slug) }}" target="_blank" class="font-medium text-gray-500 dark:text-gray-400 hover:underline">Ver</a>
                                                <a href="{{ route('posts.edit', $post) }}" wire:navigate class="font-medium text-blue-600 dark:text-blue-500 hover:underline">Editar</a>
                                                <button wire:click="deletePost({{ $post->id }})" wire:confirm="Tem certeza que deseja apagar este artigo?" class="font-medium text-red-600 dark:text-red-500 hover:underline">Apagar</button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $posts->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
