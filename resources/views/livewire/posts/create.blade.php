<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Str;

new class extends Component {
    use WithFileUploads;

    public $title = '';
    public $content = '';
    public $cover_image;

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

        $imagePath = null;
        if ($this->cover_image) {
            $imagePath = $this->cover_image->store('covers', 'public');
        }

        auth()->user()->posts()->create([
            'title' => $this->title,
            'slug' => Str::slug($this->title) . '-' . uniqid(),
            'content' => $this->content,
            'cover_image' => $imagePath,
        ]);

        session()->flash('status', 'Artigo publicado com sucesso!');
        $this->redirectRoute('posts.index', navigate: true);
    }
}; ?>

<div>
    <!-- Inclusão do Editor Trix via CDN (Muito melhor para copiar/colar) -->
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/trix@2.0.8/dist/trix.css">
    <script type="text/javascript" src="https://unpkg.com/trix@2.0.8/dist/trix.umd.min.js"></script>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Escrever Novo Artigo') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                
                <form wire:submit="save" class="space-y-6">
                    
                    <div>
                        <x-input-label for="title" :value="__('Título do Artigo')" />
                        <x-text-input wire:model="title" id="title" name="title" type="text" class="mt-1 block w-full text-lg" required autofocus placeholder="Ex: Como configurar um servidor Linux em 10 minutos" />
                        <x-input-error class="mt-2" :messages="$errors->get('title')" />
                    </div>

                    <div>
                        <x-input-label for="cover_image" :value="__('Foto de Capa (Opcional)')" />
                        
                        @if ($cover_image)
                            <div class="mt-2 mb-2">
                                <img src="{{ $cover_image->temporaryUrl() }}" class="rounded-lg shadow-sm max-h-48 object-cover">
                            </div>
                        @endif

                        <input type="file" wire:model="cover_image" id="cover_image" class="mt-1 block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400">
                        <x-input-error class="mt-2" :messages="$errors->get('cover_image')" />
                    </div>

                    <!-- Editor de Texto (Trix) -->
                    <div wire:ignore>
                        <x-input-label for="content" :value="__('Conteúdo')" class="mb-1" />
                        
                        <input id="trix_content" type="hidden" name="content" wire:model="content">
                        <trix-editor input="trix_content" class="trix-content bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-700 rounded-md shadow-sm min-h-[400px]" x-on:trix-change="$wire.content = $event.target.value" placeholder="Escreva ou cole seu artigo aqui..."></trix-editor>
                    </div>
                    <x-input-error class="mt-2" :messages="$errors->get('content')" />

                    <div class="flex items-center gap-4 mt-6">
                        <x-primary-button>{{ __('Publicar Artigo') }}</x-primary-button>

                        <x-action-message class="me-3" on="post-created">
                            {{ __('Publicado.') }}
                        </x-action-message>
                        
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

        /* Toolbar flutuante quando fixada via JS */
        trix-toolbar.trix-floating {
            position: fixed;
            top: 0;
            z-index: 50;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            background-color: #ffffff;
            border-bottom: 1px solid #e5e7eb;
        }
        .dark trix-toolbar.trix-floating {
            background-color: #1f2937;
            border-bottom-color: #374151;
        }
    </style>

    <script>
        document.addEventListener('trix-initialize', () => {
            const toolbar = document.querySelector('trix-toolbar');
            const editor  = document.querySelector('trix-editor');
            if (!toolbar || !editor) return;

            const spacer = document.createElement('div');
            spacer.style.display = 'none';
            toolbar.parentNode.insertBefore(spacer, toolbar);

            function update() {
                const rect   = editor.getBoundingClientRect();
                const height = toolbar.offsetHeight;

                if (rect.top < 0 && rect.bottom > height) {
                    spacer.style.cssText = `display:block;height:${height}px`;
                    toolbar.style.left  = rect.left + 'px';
                    toolbar.style.width = rect.width + 'px';
                    toolbar.classList.add('trix-floating');
                } else {
                    spacer.style.cssText = 'display:none';
                    toolbar.style.left  = '';
                    toolbar.style.width = '';
                    toolbar.classList.remove('trix-floating');
                }
            }

            window.addEventListener('scroll', update, { passive: true });
            window.addEventListener('resize', update, { passive: true });
        });
    </script>
</div>