<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Str;
use App\Models\Post;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use WithFileUploads;

    public Post $post;
    
    public $title = '';
    public $content = '';
    public $cover_image;
    public $existing_cover_image;

    public function mount(Post $post)
    {
        abort_if($post->user_id !== auth()->id(), 403);
        
        $this->post = $post;
        $this->title = $post->title;
        $this->content = $post->content;
        $this->existing_cover_image = $post->cover_image;
    }

    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'cover_image' => 'nullable|image|max:2048',
        ];
    }

    public function save()
    {
        $this->validate();

        $imagePath = $this->existing_cover_image;

        if ($this->cover_image) {
            if ($this->existing_cover_image) {
                Storage::disk('public')->delete($this->existing_cover_image);
            }
            $imagePath = $this->cover_image->store('covers', 'public');
        }

        $this->post->update([
            'title' => $this->title,
            'content' => $this->content,
            'cover_image' => $imagePath,
        ]);

        session()->flash('status', 'Artigo atualizado com sucesso!');
        $this->redirectRoute('posts.index', navigate: true);
    }
}; ?>

<div>
    <!-- Inclusão do Editor Trix via CDN -->
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/trix@2.0.8/dist/trix.css">
    <script type="text/javascript" src="https://unpkg.com/trix@2.0.8/dist/trix.umd.min.js"></script>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Editar Artigo') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                
                <form wire:submit="save" class="space-y-6">
                    
                    <div>
                        <x-input-label for="title" :value="__('Título do Artigo')" />
                        <x-text-input wire:model="title" id="title" name="title" type="text" class="mt-1 block w-full text-lg" required autofocus />
                        <x-input-error class="mt-2" :messages="$errors->get('title')" />
                    </div>

                    <div>
                        <x-input-label for="cover_image" :value="__('Foto de Capa (Opcional)')" />
                        
                        @if ($cover_image)
                            <div class="mt-2 mb-2">
                                <span class="block text-sm text-gray-500 mb-1">Nova imagem selecionada:</span>
                                <img src="{{ $cover_image->temporaryUrl() }}" class="rounded-lg shadow-sm max-h-48 object-cover">
                            </div>
                        @elseif ($existing_cover_image)
                            <div class="mt-2 mb-2">
                                <span class="block text-sm text-gray-500 mb-1">Imagem atual:</span>
                                <img src="{{ asset('storage/' . $existing_cover_image) }}" class="rounded-lg shadow-sm max-h-48 object-cover">
                            </div>
                        @endif

                        <input type="file" wire:model="cover_image" id="cover_image" class="mt-1 block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400">
                        <x-input-error class="mt-2" :messages="$errors->get('cover_image')" />
                    </div>

                    <!-- Editor de Texto -->
                    <div wire:ignore>
                        <x-input-label for="content" :value="__('Conteúdo')" class="mb-1" />
                        
                        <input id="trix_content" type="hidden" name="content" wire:model="content" value="{{ $content }}">
                        <trix-editor input="trix_content" class="trix-content bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-700 rounded-md shadow-sm min-h-[400px]" x-on:trix-change="$wire.content = $event.target.value"></trix-editor>
                    </div>
                    <x-input-error class="mt-2" :messages="$errors->get('content')" />

                    <div class="flex items-center gap-4 mt-6">
                        <x-primary-button>{{ __('Atualizar Artigo') }}</x-primary-button>
                        
                        <a href="{{ route('posts.index') }}" wire:navigate class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                            Cancelar
                        </a>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <style>
        /* Ajustes do Trix para Dark Mode */
        .dark trix-toolbar .trix-button-group {
            background-color: #374151;
            border-color: #4b5563;
        }
        .dark trix-toolbar .trix-button {
            background-color: #374151;
            border-color: #4b5563;
            color: #d1d5db;
        }
        .dark trix-toolbar .trix-button:hover {
            background-color: #4b5563;
        }
        .dark trix-toolbar .trix-button--icon::before {
            filter: invert(1);
        }
        .dark trix-editor {
            border-color: #374151;
        }
        .dark trix-editor:empty:not(:focus)::before {
            color: #9ca3af;
        }
    </style>
</div>