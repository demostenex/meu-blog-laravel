<?php

use Livewire\Volt\Component;
use App\Models\Post;

new class extends Component {
    public function with(): array
    {
        return [
            'posts' => auth()->user()->posts()->latest()->paginate(10),
        ];
    }
    
    public function deletePost(Post $post)
    {
        $this->authorize('delete', $post); // Vamos garantir que só você apague
        $post->delete();
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
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Seus posts publicados</h3>
                    <a href="{{ route('posts.create') }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        Novo Artigo
                    </a>
                </div>

                @if($posts->isEmpty())
                    <p class="text-gray-500 dark:text-gray-400">Você ainda não escreveu nenhum artigo. Comece agora mesmo!</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="px-6 py-3">Título</th>
                                    <th scope="col" class="px-6 py-3">Data</th>
                                    <th scope="col" class="px-6 py-3 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($posts as $post)
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700" wire:key="{{ $post->id }}">
                                        <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            {{ $post->title }}
                                        </th>
                                        <td class="px-6 py-4">
                                            {{ $post->created_at->format('d/m/Y') }}
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <a href="{{ route('posts.edit', $post) }}" wire:navigate class="font-medium text-blue-600 dark:text-blue-500 hover:underline mr-3">Editar</a>
                                            <button wire:click="deletePost({{ $post->id }})" wire:confirm="Tem certeza que deseja apagar este artigo?" class="font-medium text-red-600 dark:text-red-500 hover:underline">Apagar</button>
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