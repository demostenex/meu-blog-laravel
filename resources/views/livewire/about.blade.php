<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\User;

new #[Layout('layouts.blog')] class extends Component {
    public User $user;

    public function mount(User $user)
    {
        $this->user = $user;
    }
}; ?>

@push('seo_head')
    <title>{{ $user->name }} - {{ config('app.name') }}</title>
    <meta name="description" content="Autor: {{ $user->name }}. Artigos e publicações em {{ config('app.name') }}.">
    <link rel="canonical" href="{{ route('author.show', $user) }}">
@endpush

<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <!-- Botão Voltar Mais Elegante (Topo) -->
    <div class="mb-8">
        <a href="/" class="text-sm font-medium text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400 transition duration-150 inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Voltar para o blog
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-xl overflow-hidden border border-gray-100 dark:border-gray-700">
        
        <!-- Faixa de fundo decorativa (Hero) -->
        <div class="h-48 bg-gradient-to-r from-blue-600 to-indigo-700 w-full relative"></div>

        <div class="px-8 pb-12 flex flex-col items-center">
            <!-- Foto Flutuante do Autor (Centralizada de forma perfeita) -->
            <div class="relative -mt-24 mb-6">
                @if($user->profile_photo_path)
                    <img src="{{ image_url($user->profile_photo_path) }}" class="w-48 h-48 rounded-full border-8 border-white dark:border-gray-800 shadow-2xl object-cover bg-white" alt="{{ $user->name }}">
                @else
                    <img src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&color=7F9CF5&background=EBF4FF&size=512" class="w-48 h-48 rounded-full border-8 border-white dark:border-gray-800 shadow-2xl bg-white" alt="{{ $user->name }}">
                @endif
            </div>

            <!-- Informações (Nome Centralizado) -->
            <div class="text-center w-full">
                <h1 class="text-4xl font-extrabold text-gray-900 dark:text-white mb-2">{{ $user->name }}</h1>
                <p class="text-blue-600 dark:text-blue-400 font-bold mb-10 tracking-widest uppercase text-sm">Autor do Blog</p>

                <!-- Divisor e Bio (Título centralizado e texto estruturado) -->
                <div class="w-full mt-8 pt-8 border-t border-gray-100 dark:border-gray-700">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6 text-center">Sobre Mim</h2>
                    
                    <div class="prose prose-lg dark:prose-invert max-w-2xl mx-auto text-left text-gray-600 dark:text-gray-300 leading-relaxed whitespace-pre-line">
                        @if($user->about_me)
                            {{ $user->about_me }}
                        @else
                            <p class="italic text-gray-400 text-center">O autor ainda não escreveu uma biografia.</p>
                        @endif
                    </div>
                    <div class="flex justify-center mt-6">
                        <x-social-links :user="$user" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>