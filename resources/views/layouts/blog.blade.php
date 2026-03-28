<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <!-- Meta Tags / SEO -->
        <meta name="robots" content="index, follow">
        @stack('seo_head')
        @stack('meta')

        <!-- Favicon -->
        <link rel="icon" type="image/png" href="{{ asset('storage/favicon.png') }}?v={{ time() }}">

        <!-- Feed RSS (autodiscovery) -->
        <link rel="alternate" type="application/rss+xml" title="{{ config('app.name') }}" href="{{ route('feed') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Syntax Highlighting (Highlight.js) — tema troca com dark/light mode -->
        <link id="hljs-theme" rel="stylesheet"
              href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
        <script>
            function applyHljsTheme() {
                const isDark = document.documentElement.classList.contains('dark');
                document.getElementById('hljs-theme').href = isDark
                    ? 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css'
                    : 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css';
            }
            document.addEventListener('DOMContentLoaded', () => {
                applyHljsTheme();
                document.querySelectorAll('pre').forEach((block) => {
                    hljs.highlightElement(block);
                });
            });
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <!-- Dark Mode Script -->
        <script>
            function setDarkMode() {
                if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark')
                } else {
                    document.documentElement.classList.remove('dark')
                }
                if (typeof applyHljsTheme === 'function') applyHljsTheme();
            }
            setDarkMode();
            document.addEventListener('livewire:navigated', setDarkMode);
        </script>
    </head>
    <body x-data="{ 
        darkMode: localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)
    }" 
    class="font-sans antialiased bg-white dark:bg-gray-950 text-gray-900 dark:text-gray-100 transition-colors duration-300">
        
        <!-- Navegação Pública do Blog -->
        <nav class="border-b border-gray-200 dark:border-gray-800 bg-white/80 dark:bg-gray-900/80 backdrop-blur-md sticky top-0 z-50">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16 items-center">
                    <a href="/" class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">{{ config('app.name') }}</a>
                    
                    <div class="hidden sm:flex flex-1 mx-8 justify-end">
                        <livewire:global-search />
                    </div>

                    <div class="flex items-center gap-6">
                        <a href="/" class="text-sm font-semibold text-gray-600 hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-400 transition">Início</a>
                        @auth
                            <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-blue-600 dark:text-blue-400">Painel</a>
                        @else
                            <a href="{{ route('login') }}" class="text-sm font-semibold text-gray-600 hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-400 transition">Entrar</a>
                        @endauth
                        
                        <!-- RSS -->
                        <a href="{{ route('feed') }}" title="Feed RSS" class="p-2 rounded-md text-orange-400 hover:bg-orange-50 dark:hover:bg-orange-950/30 transition-all duration-300">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M3.75 3a.75.75 0 0 0 0 1.5c6.213 0 11.25 5.037 11.25 11.25a.75.75 0 0 0 1.5 0C16.5 8.755 10.745 3 3.75 3ZM3.75 8.5a.75.75 0 0 0 0 1.5 5.25 5.25 0 0 1 5.25 5.25.75.75 0 0 0 1.5 0A6.75 6.75 0 0 0 3.75 8.5ZM3.75 14a1.25 1.25 0 1 0 0 2.5 1.25 1.25 0 0 0 0-2.5Z"/></svg>
                        </a>

                        <!-- Theme Toggle Button Public -->
                        <button @click="darkMode = !darkMode; localStorage.theme = darkMode ? 'dark' : 'light'; if(darkMode) { document.documentElement.classList.add('dark'); } else { document.documentElement.classList.remove('dark'); }" class="p-2 rounded-md text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none transition-all duration-300" title="Alternar Tema">
                            <!-- Ícone Lua (Modo Escuro) -->
                            <svg x-show="darkMode" class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"></path></svg>
                            <!-- Ícone Sol (Modo Claro) -->
                            <svg x-show="!darkMode" class="w-5 h-5 text-gray-700" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <main>
            {{ $slot }}
        </main>

        <!-- Rodapé Público -->
        <footer class="bg-gray-50 dark:bg-gray-950 border-t border-gray-200 dark:border-gray-800 py-12 mt-20">
            <div class="max-w-5xl mx-auto px-4 text-center">
                <p class="text-gray-500 dark:text-gray-400 text-sm">
                    &copy; {{ date('Y') }} {{ config('app.name') }}. Todos os direitos reservados.
                </p>
            </div>
        </footer>
    </body>
</html>