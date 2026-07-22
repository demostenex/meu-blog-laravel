<?php

use App\Models\Document;
use App\Models\Post;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $title = '';

    public ?int $post_id = null;

    public $file;

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'post_id' => 'nullable|exists:posts,id',
            'file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip|max:10240',
        ];
    }

    public function with(): array
    {
        return [
            'documents' => Document::with('post')->latest()->get(),
            'posts' => Post::orderBy('title')->get(['id', 'title']),
        ];
    }

    public function upload(): void
    {
        $this->validate();

        $originalName = $this->file->getClientOriginalName();
        $mimeType = $this->file->getMimeType();
        $size = $this->file->getSize();
        $path = $this->file->store('documents', config('filesystems.image_disk', 'public'));

        Document::create([
            'post_id' => $this->post_id,
            'title' => $this->title,
            'path' => $path,
            'original_filename' => $originalName,
            'mime_type' => $mimeType,
            'size' => $size,
        ]);

        $this->reset('title', 'post_id', 'file');
        session()->flash('status', 'Documento enviado!');
    }

    public function delete(int $id): void
    {
        $document = Document::findOrFail($id);
        Storage::disk(config('filesystems.image_disk', 'public'))->delete($document->path);
        $document->delete();
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Documentos
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('status'))
                <div class="bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-300 text-sm px-4 py-3 rounded-lg">
                    {{ session('status') }}
                </div>
            @endif

            <!-- Formulário -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Novo Documento</h3>
                <form wire:submit="upload" class="space-y-4">
                    <div>
                        <x-input-label for="title" value="Título" />
                        <x-text-input wire:model="title" id="title" type="text" class="mt-1 block w-full" placeholder="Ex: Slides da palestra" required />
                        <x-input-error :messages="$errors->get('title')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="post_id" value="Vincular a um post (opcional)" />
                        <select wire:model="post_id" id="post_id"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">— Nenhum (documento avulso) —</option>
                            @foreach($posts as $post)
                                <option value="{{ $post->id }}">{{ $post->title }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('post_id')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="file" value="Arquivo (PDF, Office ou ZIP, até 10MB)" />
                        <input wire:model="file" id="file" type="file"
                            class="mt-1 block w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 dark:file:bg-indigo-900/30 dark:file:text-indigo-300" />
                        <div wire:loading wire:target="file" class="text-xs text-gray-400 mt-1">Enviando arquivo...</div>
                        <x-input-error :messages="$errors->get('file')" class="mt-1" />
                    </div>
                    <x-primary-button type="submit">Enviar documento</x-primary-button>
                </form>
            </div>

            <!-- Lista -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($documents as $document)
                    <div class="flex items-center justify-between px-6 py-4 gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <a href="{{ image_url($document->path) }}" target="_blank"
                                   class="font-semibold text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                    {{ $document->title }}
                                </a>
                                <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 px-2 py-0.5 rounded-full">
                                    {{ human_filesize($document->size) }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                                {{ $document->original_filename }}
                                @if($document->post)
                                    &bull; vinculado a <span class="italic">{{ $document->post->title }}</span>
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button wire:click="delete({{ $document->id }})"
                                wire:confirm="Remover '{{ $document->title }}'? Esta ação não pode ser desfeita."
                                type="button"
                                class="text-sm text-red-600 dark:text-red-400 hover:underline">
                                Excluir
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                        Nenhum documento enviado ainda.
                    </div>
                @endforelse
            </div>

        </div>
    </div>
</div>
