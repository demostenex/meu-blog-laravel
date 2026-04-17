<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Tag;
use App\Services\ImageService;
use App\Services\ImagenService;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use WithFileUploads;

    public $title = '';
    public $content = '';
    public $cover_image;
    public $trixImage = null;
    public $trixVideo = null;

    public string $cover_image_prompt = '';
    public bool $cover_image_use_content = false;
    public bool $cover_image_use_bio = false;
    public ?string $ai_generated_cover_path = null;
    public bool $generatingCover = false;
    public ?string $coverStatus = null;

    public ?int $category_id = null;
    public string $tags_input = '';

    public function rules()
    {
        return [
            'title'       => 'required|string|max:255',
            'content'     => 'required|string',
            'cover_image' => 'nullable|image|max:2048',
            'trixImage'   => 'nullable|image|max:5120',
            'trixVideo'   => 'nullable|file|mimetypes:video/mp4,video/webm,video/ogg,video/quicktime|max:102400',
            'category_id' => 'nullable|exists:categories,id',
            'tags_input'  => 'nullable|string|max:500',
        ];
    }

    public function with(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(),
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

    private function storePost(bool $publish): \App\Models\Post
    {
        $this->validate();

        $imagePath = null;
        if ($this->cover_image) {
            $imagePath = app(ImageService::class)->storeCompressed($this->cover_image, 'covers', 1920, 1080);
        } elseif ($this->ai_generated_cover_path) {
            $imagePath = $this->ai_generated_cover_path;
        }

        $post = auth()->user()->posts()->create([
            'title'                   => $this->title,
            'slug'                    => Str::slug($this->title) . '-' . uniqid(),
            'content'                 => $this->content,
            'cover_image'             => $imagePath,
            'cover_image_prompt'      => $this->cover_image_prompt ?: null,
            'cover_image_use_content' => $this->cover_image_use_content,
            'cover_image_use_bio'     => $this->cover_image_use_bio,
            'published_at'            => $publish ? now() : null,
            'category_id'             => $this->category_id ?: null,
        ]);

        if ($this->tags_input) {
            $tagIds = collect(explode(',', $this->tags_input))
                ->map(fn($t) => trim($t))
                ->filter()
                ->map(fn($name) => Tag::firstOrCreate(
                    ['slug' => Str::slug($name)],
                    ['name' => $name]
                ))
                ->pluck('id');
            $post->tags()->sync($tagIds);
        }

        return $post;
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
            if ($this->ai_generated_cover_path) {
                Storage::disk('public')->delete($this->ai_generated_cover_path);
                $this->ai_generated_cover_path = null;
            }

            $tempPost = new \App\Models\Post([
                'title'                   => $this->title ?: 'Artigo sem título',
                'content'                 => $this->content,
                'cover_image_prompt'      => $this->cover_image_prompt,
                'cover_image_use_content' => $this->cover_image_use_content,
                'cover_image_use_bio'     => $this->cover_image_use_bio,
            ]);

            $path = app(ImagenService::class)->generateCoverImage($tempPost, $user);
            $this->ai_generated_cover_path = $path;
            $this->coverStatus = 'success';
        } catch (\Throwable $e) {
            $this->coverStatus = 'error:' . $e->getMessage();
        } finally {
            $this->generatingCover = false;
        }
    }

    public function save()
    {
        $this->storePost(true);
        session()->flash('status', 'Artigo publicado com sucesso!');
        $this->redirectRoute('posts.index', navigate: true);
    }

    public function saveDraft()
    {
        $post = $this->storePost(false);
        session()->flash('status', 'Rascunho salvo com sucesso!');
        $this->redirectRoute('posts.edit', ['post' => $post], navigate: true);
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

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="category_id" :value="__('Categoria')" />
                            <select wire:model="category_id" id="category_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">Sem categoria</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-1" :messages="$errors->get('category_id')" />
                        </div>
                        <div>
                            <x-input-label for="tags_input" :value="__('Tags (separe por vírgula)')" />
                            <x-text-input wire:model="tags_input" id="tags_input" type="text" class="mt-1 block w-full" placeholder="Ex: Laravel, Docker, PHP" />
                            <x-input-error class="mt-1" :messages="$errors->get('tags_input')" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="cover_image" :value="__('Foto de Capa (Opcional)')" />
                        
                        @if ($cover_image)
                            <div class="mt-2 mb-2">
                                <img src="{{ $cover_image->temporaryUrl() }}" class="rounded-lg shadow-sm max-h-48 object-cover">
                            </div>
                        @elseif ($ai_generated_cover_path)
                            <div class="mt-2 mb-2">
                                <span class="block text-sm text-gray-500 mb-1">Capa gerada pela IA:</span>
                                <img src="{{ asset('storage/' . $ai_generated_cover_path) }}" class="rounded-lg shadow-sm max-h-48 object-cover">
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
                                    {{ $ai_generated_cover_path ? '🔄 Regenerar capa' : '✨ Gerar capa' }}
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

                    <!-- Editor de Texto (Trix) -->
                    <div wire:ignore>
                        <x-input-label for="content" :value="__('Conteúdo')" class="mb-1" />
                        <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                            Arraste/cole imagens ou vídeos no editor. Vídeos serão convertidos automaticamente para player no artigo.
                        </p>
                        
                        <input id="trix_content" type="hidden" name="content" wire:model="content">
                        <trix-editor input="trix_content" class="trix-content bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-700 rounded-md shadow-sm min-h-[400px]" x-on:trix-change="$wire.content = $event.target.value" placeholder="Escreva ou cole seu artigo aqui..."></trix-editor>
                    </div>
                    <x-input-error class="mt-2" :messages="$errors->get('content')" />

                    <div class="flex items-center gap-4 mt-6">
                        <x-primary-button wire:click="save" wire:loading.attr="disabled" type="button">
                            <span wire:loading.remove wire:target="save">{{ __('Publicar Artigo') }}</span>
                            <span wire:loading wire:target="save">Publicando...</span>
                        </x-primary-button>

                        <x-secondary-button wire:click="saveDraft" wire:loading.attr="disabled" type="button">
                            <span wire:loading.remove wire:target="saveDraft">💾 Salvar Rascunho</span>
                            <span wire:loading wire:target="saveDraft">Salvando...</span>
                        </x-secondary-button>
                        
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

        // Upload de imagens inline no Trix via Livewire
        (function () {
            const state = window.__trixInlineUploadState ??= {
                pendingImageAttachment: null,
                pendingVideoEditor: null,
                pendingVideoAttachment: null,
            };

            document.addEventListener('trix-attachment-add', function (event) {
                const attachment = event.attachment;
                if (!attachment.file) return;

                const fileType = attachment.file.type || '';
                const fileName = (attachment.file.name || '').toLowerCase();
                const fileExtension = fileName.includes('.') ? fileName.split('.').pop() : '';
                const videoExtensions = ['mp4', 'webm', 'ogg', 'ogv', 'mov', 'm4v'];
                const isVideo = fileType.startsWith('video/') || videoExtensions.includes(fileExtension);
                const isImage = fileType.startsWith('image/');
                console.log('[Trix] arquivo detectado:', attachment.file.name);
                attachment.setUploadProgress(0);

                const wireEl = event.target?.closest('[wire\\:id]');
                if (!wireEl) { console.warn('[Trix] wire:id não encontrado'); return; }
                const component = Livewire.find(wireEl.getAttribute('wire:id'));
                if (!component) { console.warn('[Trix] componente Livewire não encontrado'); return; }

                if (!isVideo && !isImage) {
                    console.warn('[Trix] tipo de arquivo não suportado para upload inline:', fileType || '(sem MIME)');
                    if (typeof attachment.remove === 'function') attachment.remove();
                    return;
                }

                if (isVideo) {
                    state.pendingVideoEditor = event.target;
                    state.pendingVideoAttachment = attachment;
                    component.upload(
                        'trixVideo',
                        attachment.file,
                        () => {
                            console.log('[Trix] upload de vídeo concluído, chamando storeTrixVideo');
                            component.call('storeTrixVideo');
                        },
                        () => {
                            console.warn('[Trix] erro no upload de vídeo');
                            state.pendingVideoEditor = null;
                            state.pendingVideoAttachment = null;
                        },
                        (progressEvent) => {
                            const progress = progressEvent?.detail?.progress
                                ?? (progressEvent?.total ? Math.round(progressEvent.loaded / progressEvent.total * 100) : 0);
                            attachment.setUploadProgress(progress);
                        }
                    );
                    return;
                }

                state.pendingImageAttachment = attachment;
                component.upload(
                    'trixImage',
                    attachment.file,
                    () => {
                        console.log('[Trix] upload concluído, chamando storeTrixImage');
                        component.call('storeTrixImage');
                    },
                    () => {
                        console.warn('[Trix] erro no upload');
                        state.pendingImageAttachment = null;
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
                if (state.pendingImageAttachment && url) {
                    state.pendingImageAttachment.setAttributes({ url, href: url });
                    state.pendingImageAttachment = null;
                }
            });

            window.addEventListener('trix-video-ready', (event) => {
                console.log('[Trix] trix-video-ready recebido', event.detail);
                const url = event.detail?.url;
                if (state.pendingVideoEditor && url) {
                    state.pendingVideoEditor.editor.insertString(`\n[[video:${url}]]\n`);
                    if (state.pendingVideoAttachment && typeof state.pendingVideoAttachment.remove === 'function') {
                        state.pendingVideoAttachment.remove();
                    }
                    state.pendingVideoEditor = null;
                    state.pendingVideoAttachment = null;
                }
            });
        })();
    </script>
</div>
