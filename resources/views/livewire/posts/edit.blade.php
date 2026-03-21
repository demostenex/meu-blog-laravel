<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Str;
use App\Models\Post;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use WithFileUploads;

    public Post $post;
    
    public $title = '';
    public $content = '';
    public $cover_image;
    public $existing_cover_image;

    public bool $generatingComment = false;
    public ?string $commentStatus = null;

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

    public function generateAiComment()
    {
        $user = auth()->user();

        if (! $user->gemini_api_key) {
            $this->commentStatus = 'error:Configure a chave de API do Gemini no seu perfil primeiro.';
            return;
        }

        $this->generatingComment = true;
        $this->commentStatus = null;

        try {
            $service = app(GeminiService::class);
            $service->generateComment($this->post, $user);
            $this->post->refresh();
            $this->commentStatus = 'success';
        } catch (\Throwable $e) {
            $this->commentStatus = 'error:' . $e->getMessage();
        } finally {
            $this->generatingComment = false;
        }
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

            <!-- Seção IA Comentarista -->
            <div class="mt-8 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <span>🤖</span> Comentário da IA
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            O bot vai ler o artigo e comentar de forma sarcástica. Você pode regenerar quando quiser.
                        </p>
                    </div>

                    <button
                        wire:click="generateAiComment"
                        wire:loading.attr="disabled"
                        wire:target="generateAiComment"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors"
                    >
                        <span wire:loading.remove wire:target="generateAiComment">
                            {{ $post->latestAiComment ? '🔄 Regenerar' : '✨ Gerar comentário' }}
                        </span>
                        <span wire:loading wire:target="generateAiComment" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                            </svg>
                            Gerando...
                        </span>
                    </button>
                </div>

                @if ($commentStatus === 'success')
                    <p class="text-sm text-green-600 dark:text-green-400 mb-3">✅ Comentário gerado com sucesso!</p>
                @elseif ($commentStatus && str_starts_with($commentStatus, 'error:'))
                    <p class="text-sm text-red-600 dark:text-red-400 mb-3">❌ {{ str_replace('error:', '', $commentStatus) }}</p>
                @endif

                @if ($post->latestAiComment)
                    @php $aiComment = $post->latestAiComment; $aiUser = $post->user; @endphp
                    <div class="border border-purple-200 dark:border-purple-800 rounded-xl p-4 bg-purple-50 dark:bg-purple-950/30">
                        <div class="flex items-center gap-3 mb-3">
                            @if($aiUser->gemini_ai_photo)
                                <img src="{{ asset('storage/' . $aiUser->gemini_ai_photo) }}" class="w-9 h-9 rounded-full object-cover" alt="{{ $aiUser->gemini_ai_name }}">
                            @else
                                <div class="w-9 h-9 rounded-full bg-purple-200 dark:bg-purple-800 flex items-center justify-center text-lg">🤖</div>
                            @endif
                            <div>
                                <p class="font-semibold text-sm text-gray-900 dark:text-gray-100">{{ $aiUser->gemini_ai_name ?: 'BOT Sarcástico' }}</p>
                                <p class="text-xs text-gray-400">{{ $aiComment->model }} &bull; {{ $aiComment->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap leading-relaxed">{{ $aiComment->content }}</div>
                    </div>
                @else
                    <p class="text-sm text-gray-400 italic">Nenhum comentário gerado ainda.</p>
                @endif
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

            // Espaçador para evitar salto de layout quando a toolbar for fixada
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