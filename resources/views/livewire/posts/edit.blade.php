<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Str;
use App\Models\Post;
use App\Services\GeminiService;
use App\Services\ImagenService;
use App\Services\ImageService;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use WithFileUploads;

    public Post $post;
    
    public $title = '';
    public $content = '';
    public $cover_image;
    public $existing_cover_image;
    public $trixImage = null;
    public $trixVideo = null;

    public string $cover_image_prompt = '';
    public bool $cover_image_use_content = false;
    public bool $cover_image_use_bio = false;
    public bool $generatingCover = false;
    public ?string $coverStatus = null;

    public bool $generatingComment = false;
    public ?string $commentStatus = null;

    public function mount(Post $post)
    {
        abort_if($post->user_id !== auth()->id(), 403);
        
        $this->post = $post;
        $this->title = $post->title;
        $this->content = $post->content;
        $this->existing_cover_image = $post->cover_image;
        $this->cover_image_prompt = $post->cover_image_prompt ?? '';
        $this->cover_image_use_content = (bool) $post->cover_image_use_content;
        $this->cover_image_use_bio = (bool) $post->cover_image_use_bio;
    }

    public function rules()
    {
        return [
            'title'       => 'required|string|max:255',
            'content'     => 'required|string',
            'cover_image' => 'nullable|image|max:2048',
            'trixImage'   => 'nullable|image|max:5120',
            'trixVideo'   => 'nullable|file|mimetypes:video/mp4,video/webm,video/ogg,video/quicktime|max:102400',
        ];
    }

    public function storeTrixImage(): void
    {
        $this->validate(['trixImage' => 'required|image|max:5120']);
        $path = app(ImageService::class)->storeCompressed($this->trixImage, 'post-images', 1200, 1200, 80);
        $this->dispatch('trix-image-ready', url: asset('storage/' . $path));
        $this->trixImage = null;
    }

    public function storeTrixVideo(): void
    {
        $this->validate(['trixVideo' => 'required|file|mimetypes:video/mp4,video/webm,video/ogg,video/quicktime|max:102400']);
        $path = $this->trixVideo->store('post-videos', 'public');
        $this->dispatch('trix-video-ready', url: asset('storage/' . $path));
        $this->trixVideo = null;
    }

    private function updatePost(): void
    {
        $this->validate();

        $imagePath = $this->existing_cover_image;

        if ($this->cover_image) {
            if ($this->existing_cover_image) {
                Storage::disk('public')->delete($this->existing_cover_image);
            }
            $imagePath = app(ImageService::class)->storeCompressed($this->cover_image, 'covers', 1920, 1080);
        }

        $this->post->update([
            'title'                   => $this->title,
            'content'                 => $this->content,
            'cover_image'             => $imagePath,
            'cover_image_prompt'      => $this->cover_image_prompt ?: null,
            'cover_image_use_content' => $this->cover_image_use_content,
            'cover_image_use_bio'     => $this->cover_image_use_bio,
        ]);
    }

    public function save()
    {
        $this->updatePost();
        session()->flash('status', 'Artigo atualizado com sucesso!');
        $this->redirectRoute('posts.index', navigate: true);
    }

    public function saveDraft()
    {
        $this->updatePost();
        $this->post->update(['published_at' => null]);
        session()->flash('status', 'Salvo como rascunho.');
        $this->redirectRoute('posts.edit', ['post' => $this->post], navigate: true);
    }

    public function publish()
    {
        $this->updatePost();
        $this->post->update(['published_at' => $this->post->published_at ?? now()]);
        session()->flash('status', 'Artigo publicado com sucesso!');
        $this->redirectRoute('posts.index', navigate: true);
    }

    public function generateAiCover(): void
    {
        $this->validate(['cover_image_prompt' => 'required|string|max:2000']);

        $user = auth()->user();

        if (! $user->gemini_api_key) {
            $this->coverStatus = 'error:Configure a chave de API do Gemini no seu perfil primeiro.';
            return;
        }

        $this->generatingCover = true;
        $this->coverStatus = null;

        try {
            $this->post->update([
                'cover_image_prompt'      => $this->cover_image_prompt,
                'cover_image_use_content' => $this->cover_image_use_content,
                'cover_image_use_bio'     => $this->cover_image_use_bio,
            ]);
            $this->post->refresh();

            if ($this->existing_cover_image) {
                Storage::disk('public')->delete($this->existing_cover_image);
            }

            $path = app(ImagenService::class)->generateCoverImage($this->post, $user);

            $this->post->update(['cover_image' => $path]);
            $this->post->refresh();
            $this->existing_cover_image = $path;
            $this->cover_image = null;
            $this->coverStatus = 'success';
        } catch (\Throwable $e) {
            $this->coverStatus = 'error:' . $e->getMessage();
        } finally {
            $this->generatingCover = false;
        }
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
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight flex items-center gap-3">
                {{ __('Editar Artigo') }}
                @if($post->isPublished())
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-400">
                        ✅ Publicado
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-400">
                        📝 Rascunho
                    </span>
                @endif
            </h2>
            <a href="{{ route('posts.show', $post->slug) }}" target="_blank"
               class="inline-flex items-center gap-1.5 text-sm text-blue-600 dark:text-blue-400 hover:underline">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Visualizar
            </a>
        </div>
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

                    <!-- Gerador de Capa com IA -->
                    <div class="border border-indigo-200 dark:border-indigo-800 rounded-xl p-4 bg-indigo-50 dark:bg-indigo-950/30 space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">🎨</span>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Gerar capa com IA</h3>
                        </div>

                        <div>
                            <x-input-label for="cover_image_prompt" :value="__('Descreva a imagem desejada *')" />
                            <textarea
                                wire:model="cover_image_prompt"
                                id="cover_image_prompt"
                                rows="3"
                                placeholder="Ex: Um homem de costas olhando para um monitor com código, estilo cinematográfico, tons escuros..."
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:placeholder-gray-500 shadow-sm focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 text-sm"
                            ></textarea>
                            <x-input-error class="mt-1" :messages="$errors->get('cover_image_prompt')" />
                        </div>

                        <div class="flex flex-wrap gap-4">
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                <input type="checkbox" wire:model="cover_image_use_content" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                Incluir conteúdo do artigo no contexto
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                <input type="checkbox" wire:model="cover_image_use_bio" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                Incluir bio do autor no contexto
                            </label>
                        </div>

                        <div class="flex items-center gap-3">
                            <button
                                wire:click="generateAiCover"
                                wire:loading.attr="disabled"
                                wire:target="generateAiCover"
                                type="button"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors"
                            >
                                <span wire:loading.remove wire:target="generateAiCover">
                                    {{ $existing_cover_image ? '🔄 Regenerar capa' : '✨ Gerar capa' }}
                                </span>
                                <span wire:loading wire:target="generateAiCover" class="flex items-center gap-2">
                                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                                    </svg>
                                    Gerando...
                                </span>
                            </button>

                            @if ($coverStatus === 'success')
                                <span class="text-sm text-green-600 dark:text-green-400">✅ Capa gerada com sucesso!</span>
                            @elseif ($coverStatus && str_starts_with($coverStatus, 'error:'))
                                <span class="text-sm text-red-600 dark:text-red-400">❌ {{ str_replace('error:', '', $coverStatus) }}</span>
                            @endif
                        </div>
                    </div>

                    <!-- Editor de Texto -->
                    <div wire:ignore>
                        <x-input-label for="content" :value="__('Conteúdo')" class="mb-1" />
                        <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                            Arraste/cole imagens ou vídeos no editor. Vídeos serão convertidos automaticamente para player no artigo.
                        </p>
                        
                        <input id="trix_content" type="hidden" name="content" wire:model="content" value="{{ $content }}">
                        <trix-editor input="trix_content" class="trix-content bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-700 rounded-md shadow-sm min-h-[400px]" x-on:trix-change="$wire.content = $event.target.value"></trix-editor>
                    </div>
                    <x-input-error class="mt-2" :messages="$errors->get('content')" />

                    <div class="flex items-center flex-wrap gap-3 mt-6">
                        @if($post->isPublished())
                            <x-primary-button wire:click="save" wire:loading.attr="disabled" type="button">
                                <span wire:loading.remove wire:target="save">{{ __('Salvar Alterações') }}</span>
                                <span wire:loading wire:target="save">Salvando...</span>
                            </x-primary-button>
                            <x-secondary-button wire:click="saveDraft" wire:loading.attr="disabled" type="button">
                                <span wire:loading.remove wire:target="saveDraft">📝 Voltar para Rascunho</span>
                                <span wire:loading wire:target="saveDraft">Salvando...</span>
                            </x-secondary-button>
                        @else
                            <x-primary-button wire:click="publish" wire:loading.attr="disabled" type="button">
                                <span wire:loading.remove wire:target="publish">🚀 Publicar Artigo</span>
                                <span wire:loading wire:target="publish">Publicando...</span>
                            </x-primary-button>
                            <x-secondary-button wire:click="save" wire:loading.attr="disabled" type="button">
                                <span wire:loading.remove wire:target="save">💾 Salvar Rascunho</span>
                                <span wire:loading wire:target="save">Salvando...</span>
                            </x-secondary-button>
                        @endif
                        
                        <a href="{{ route('posts.index') }}" wire:navigate class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 text-sm">
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

        // Upload de imagens inline no Trix via Livewire
        (function () {
            let pendingImageAttachment = null;
            let pendingVideoEditor = null;
            let pendingVideoAttachment = null;

            document.addEventListener('trix-attachment-add', function (event) {
                const attachment = event.attachment;
                if (!attachment.file) return;

                const isVideo = attachment.file.type?.startsWith('video/');
                console.log('[Trix] arquivo detectado:', attachment.file.name);
                attachment.setUploadProgress(0);

                const wireEl = event.target?.closest('[wire\\:id]');
                if (!wireEl) { console.warn('[Trix] wire:id não encontrado'); return; }
                const component = Livewire.find(wireEl.getAttribute('wire:id'));
                if (!component) { console.warn('[Trix] componente Livewire não encontrado'); return; }

                if (isVideo) {
                    pendingVideoEditor = event.target;
                    pendingVideoAttachment = attachment;
                    component.upload(
                        'trixVideo',
                        attachment.file,
                        () => {
                            console.log('[Trix] upload de vídeo concluído, chamando storeTrixVideo');
                            component.call('storeTrixVideo');
                        },
                        () => {
                            console.warn('[Trix] erro no upload de vídeo');
                            pendingVideoEditor = null;
                            pendingVideoAttachment = null;
                        },
                        (progressEvent) => {
                            const progress = progressEvent?.detail?.progress
                                ?? (progressEvent?.total ? Math.round(progressEvent.loaded / progressEvent.total * 100) : 0);
                            attachment.setUploadProgress(progress);
                        }
                    );
                    return;
                }

                pendingImageAttachment = attachment;
                component.upload(
                    'trixImage',
                    attachment.file,
                    () => {
                        console.log('[Trix] upload concluído, chamando storeTrixImage');
                        component.call('storeTrixImage');
                    },
                    () => {
                        console.warn('[Trix] erro no upload');
                        pendingImageAttachment = null;
                    },
                    (progressEvent) => {
                        const progress = progressEvent?.detail?.progress
                            ?? (progressEvent?.total ? Math.round(progressEvent.loaded / progressEvent.total * 100) : 0);
                        attachment.setUploadProgress(progress);
                    }
                );
            });

            window.addEventListener('trix-image-ready', (event) => {
                console.log('[Trix] trix-image-ready recebido', event.detail);
                const url = event.detail?.url;
                if (pendingImageAttachment && url) {
                    pendingImageAttachment.setAttributes({ url, href: url });
                    pendingImageAttachment = null;
                }
            });

            window.addEventListener('trix-video-ready', (event) => {
                console.log('[Trix] trix-video-ready recebido', event.detail);
                const url = event.detail?.url;
                if (pendingVideoEditor && url) {
                    pendingVideoEditor.editor.insertString(`\n[[video:${url}]]\n`);
                    if (pendingVideoAttachment && typeof pendingVideoAttachment.remove === 'function') {
                        pendingVideoAttachment.remove();
                    }
                    pendingVideoEditor = null;
                    pendingVideoAttachment = null;
                }
            });
        })();
    </script>
</div>
