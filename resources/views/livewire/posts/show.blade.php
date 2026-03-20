<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Post;

new #[Layout('layouts.blog')] class extends Component {
    public Post $post;

    public function mount(Post $post)
    {
        $this->post = $post;
    }
}; ?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 flex flex-col lg:flex-row gap-12 items-start">
    
    <!-- Conteúdo Principal -->
    <div class="flex-1 min-w-0 max-w-3xl w-full">
        <article>
            <!-- Título -->
            <header class="mb-12 text-center lg:text-left">
                <div class="mb-4">
                    <span class="inline-flex items-center rounded-full bg-blue-50 dark:bg-blue-900/30 px-3 py-1 text-xs font-medium text-blue-700 dark:text-blue-300">
                        Artigo
                    </span>
                </div>
                <h1 class="text-4xl font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-5xl lg:text-6xl mb-6">
                    {{ $post->title }}
                </h1>
                <div class="flex items-center justify-center lg:justify-start gap-3 text-sm text-gray-500 dark:text-gray-400">
                    <a href="{{ route('author.show', $post->user) }}" class="flex items-center gap-2 hover:opacity-80 transition-opacity">
                        @if($post->user->profile_photo_path)
                            <img src="{{ asset('storage/' . $post->user->profile_photo_path) }}" class="w-8 h-8 rounded-full object-cover" alt="{{ $post->user->name }}">
                        @else
                            <img src="https://ui-avatars.com/api/?name={{ urlencode($post->user->name) }}&color=7F9CF5&background=EBF4FF" class="w-8 h-8 rounded-full" alt="{{ $post->user->name }}">
                        @endif
                        <span>Por <strong>{{ $post->user->name }}</strong></span>
                    </a>
                    <span>&bull;</span>
                    <time>{{ $post->created_at->format('d/m/Y') }}</time>
                </div>
            </header>

            <!-- Imagem de Capa -->
            @if($post->cover_image)
                <div class="mb-12">
                    <img src="{{ asset('storage/' . $post->cover_image) }}" alt="{{ $post->title }}" class="w-full rounded-2xl shadow-xl object-cover max-h-[500px]">
                </div>
            @endif

            <!-- Conteúdo do Artigo -->
            <div id="article-content" class="trix-content text-gray-800 dark:text-gray-200 leading-relaxed text-lg sm:text-xl selection:bg-blue-100 dark:selection:bg-blue-900">
                {!! $post->content !!}
            </div>
        </article>

        <!-- Footer do Artigo -->
        <div class="mt-20 pt-10 border-t border-gray-100 dark:border-gray-800">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-6 mb-12">
                <a href="/" class="text-sm font-semibold text-blue-600 dark:text-blue-400 hover:underline">&larr; Voltar para a página inicial</a>
            </div>

            <!-- Seção de Comentários Disqus -->
            <div id="disqus_thread" class="mt-12"></div>
            <script>
                var disqus_config = function () {
                    this.page.url = "{{ route('posts.show', $post->slug) }}";
                    this.page.identifier = "{{ $post->id }}";
                };
                (function() {
                    var d = document, s = d.createElement('script');
                    s.src = 'https://demostenes-blog.disqus.com/embed.js';
                    s.setAttribute('data-timestamp', +new Date());
                    (d.head || d.body).appendChild(s);
                })();
            </script>
            <noscript>Por favor, habilite o JavaScript para visualizar os <a href="https://disqus.com/?ref_noscript">comentários via Disqus.</a></noscript>
        </div>
    </div>

    <!-- Barra Lateral (On This Page / Table of Contents) -->
    <aside class="hidden lg:block w-64 shrink-0">
        <div class="sticky top-24">
            <h3 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-widest mb-4 border-l-2 border-blue-500 pl-3">
                Nesta Página
            </h3>
            <ul id="toc-list" class="space-y-3 text-sm text-gray-500 dark:text-gray-400">
                <!-- Gerado via JavaScript -->
            </ul>
        </div>
    </aside>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const content = document.getElementById('article-content');
            const tocList = document.getElementById('toc-list');
            
            // Busca títulos padrões
            let headings = Array.from(content.querySelectorAll('h1, h2, h3'));
            
            // Busca textos apenas em negrito que atuam como títulos (comportamento comum de colar texto no Trix)
            const strongs = Array.from(content.querySelectorAll('strong'));
            strongs.forEach(strong => {
                const parent = strong.parentElement;
                // Se o texto do <strong/b> for praticamente todo o texto da <div> pai, é um título de seção!
                if (parent && (parent.tagName === 'DIV' || parent.tagName === 'P') && strong.textContent.trim().length > 10) {
                    if (parent.textContent.trim() === strong.textContent.trim()) {
                        headings.push(strong);
                    }
                }
            });

            // Ordena todos os títulos encontrados pela ordem que aparecem no artigo
            headings.sort((a, b) => {
                if (a === b) return 0;
                return a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1;
            });
            
            if (headings.length === 0) {
                tocList.innerHTML = '<li class="italic text-gray-400 text-xs">Nenhum sumário disponível.</li>';
                return;
            }

            headings.forEach((heading, index) => {
                // Cria um ID único para cada título se ele não tiver um
                let id = heading.getAttribute('id');
                if (!id) {
                    // Pega até os primeiros 30 caracteres para formar a URL
                    id = 'secao-' + index + '-' + heading.innerText.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '').substring(0, 30);
                    heading.setAttribute('id', id);
                }

                // Cria o item da lista
                const li = document.createElement('li');
                const a = document.createElement('a');
                a.href = '#' + id;
                a.innerText = heading.innerText;
                a.className = 'hover:text-blue-600 dark:hover:text-blue-400 transition-colors block line-clamp-2 leading-tight';
                
                // Aplica indentação dependendo se é H2 ou H3
                if (heading.tagName === 'H2') {
                    a.classList.add('ml-2');
                } else if (heading.tagName === 'H3') {
                    a.classList.add('ml-4');
                }

                li.appendChild(a);
                tocList.appendChild(li);
            });
        });
    </script>

    <style>
        /* Estilos Semânticos para o Conteúdo do Artigo (Trix) */
        .trix-content h1 { font-size: 2.5rem; font-weight: 800; margin: 2rem 0 1rem; color: var(--tw-prose-headings); scroll-margin-top: 6rem; }
        .trix-content h2 { font-size: 1.875rem; font-weight: 700; margin: 1.5rem 0 0.75rem; scroll-margin-top: 6rem; }
        .trix-content h3 { font-size: 1.5rem; font-weight: 600; margin: 1.25rem 0 0.5rem; scroll-margin-top: 6rem; }
        .trix-content p { margin-bottom: 1.5rem; line-height: 1.8; }
        .trix-content ul { list-style-type: disc; padding-left: 1.5rem; margin-bottom: 1.5rem; }
        .trix-content ol { list-style-type: decimal; padding-left: 1.5rem; margin-bottom: 1.5rem; }
        .trix-content li { margin-bottom: 0.5rem; }
        .trix-content blockquote { 
            border-left: 4px solid #3b82f6; 
            padding: 0.5rem 0 0.5rem 1.5rem; 
            font-style: italic; 
            color: #4b5563; 
            background: #f9fafb;
            margin: 2rem 0;
            border-radius: 0 0.5rem 0.5rem 0;
        }
        .dark .trix-content blockquote { background: #1f2937; color: #9ca3af; }
        .trix-content img { border-radius: 1rem; width: 100%; height: auto; margin: 2rem 0; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .trix-content a { color: #3b82f6; text-decoration: underline; text-underline-offset: 4px; font-weight: 500; }

        /* Estilo para Blocos de Código (Highlight.js) */
        .trix-content pre {
            background-color: #0d1117 !important; /* GitHub Dark Base */
            color: #c9d1d9 !important;
            padding: 1.5rem;
            border-radius: 0.75rem;
            overflow-x: auto;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 0.9em;
            line-height: 1.5;
            margin: 2rem 0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
    </style>
</div>