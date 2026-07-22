<?php

use App\Models\Document;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.blog')] class extends Component
{
    public function with(): array
    {
        return [
            'documents' => Document::with('post')->latest()->paginate(12),
        ];
    }
}; ?>

@push('seo_head')
    <title>Biblioteca - {{ config('app.name') }}</title>
    <meta name="description" content="Documentos e materiais complementares disponíveis para download.">
    <link rel="canonical" href="{{ route('documents.library') }}">
@endpush

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">

    <!-- Hero -->
    <header class="mb-14">
        <p class="text-sm font-semibold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest mb-3">Biblioteca</p>
        <h1 class="text-4xl sm:text-5xl font-extrabold text-gray-900 dark:text-white tracking-tight mb-3">Documentos</h1>
        <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl leading-relaxed">Materiais complementares disponíveis para download.</p>
        <p class="mt-4 text-sm text-gray-400 dark:text-gray-500">
            {{ $documents->total() }} {{ $documents->total() === 1 ? 'documento disponível' : 'documentos disponíveis' }}
        </p>
    </header>

    <!-- Lista -->
    @if($documents->isNotEmpty())
    <div class="space-y-4">
        @foreach($documents as $document)
            <a href="{{ image_url($document->path) }}" download="{{ $document->original_filename }}"
               class="group flex items-center gap-4 rounded-xl border border-gray-100 dark:border-gray-800 hover:border-indigo-200 dark:hover:border-indigo-800 p-5 transition-all">
                <div class="shrink-0 w-12 h-12 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase">
                    {{ Str::afterLast($document->original_filename, '.') }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-base font-semibold text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors truncate">
                        {{ $document->title }}
                    </p>
                    <div class="flex items-center gap-2 mt-1 text-xs text-gray-400">
                        <span>{{ human_filesize($document->size) }}</span>
                        @if($document->post)
                            <span>&bull;</span>
                            <span>do artigo <span class="italic">{{ $document->post->title }}</span></span>
                        @endif
                    </div>
                </div>
                <span class="shrink-0 text-sm text-indigo-500 dark:text-indigo-400 font-semibold group-hover:underline">Baixar &darr;</span>
            </a>
        @endforeach
    </div>
    @else
        <p class="text-gray-500 dark:text-gray-400 text-center py-12">Nenhum documento disponível ainda.</p>
    @endif

    @if($documents->hasPages())
        <div class="mt-16">
            {{ $documents->links() }}
        </div>
    @endif
</div>
