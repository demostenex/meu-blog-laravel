<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Post;

new #[Layout('layouts.blog')] class extends Component {
    public Post $post;

    public function mount(Post $post)
    {
        $this->post = $post->load('category', 'tags');

        // Rascunho só pode ser visto pelo dono
        if (! $post->isPublished()) {
            abort_if(auth()->id() !== $post->user_id, 404);
        }

        if (! auth()->check() || auth()->id() !== $post->user_id) {
            $post->incrementViews();
        }
    }
}; ?>

@php $description = Str::limit(strip_tags($post->content), 160); @endphp

@push('seo_head')
    <title>{{ $post->title }} - {{ config('app.name') }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ route('posts.show', $post->slug) }}">
@endpush

@push('meta')
    <meta property="og:title" content="{{ $post->title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:type" content="article">
    <meta property="og:url" content="{{ route('posts.show', $post->slug) }}">
    @if($post->cover_image)
        <meta property="og:image" content="{{ secure_asset('storage/' . $post->cover_image) }}">
        <meta property="og:image:secure_url" content="{{ secure_asset('storage/' . $post->cover_image) }}">
        <meta property="og:image:type" content="image/{{ pathinfo($post->cover_image, PATHINFO_EXTENSION) }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta itemprop="image" content="{{ secure_asset('storage/' . $post->cover_image) }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="{{ secure_asset('storage/' . $post->cover_image) }}">
    @else
        <meta name="twitter:card" content="summary">
    @endif
    <meta name="twitter:title" content="{{ $post->title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <script type="application/ld+json">
    @php
    $jsonLd = [
        '@context'      => 'https://schema.org',
        '@type'         => 'Article',
        'headline'      => $post->title,
        'description'   => $description,
        'datePublished' => $post->published_at?->toIso8601String() ?? $post->created_at->toIso8601String(),
        'dateModified'  => $post->updated_at->toIso8601String(),
        'url'           => route('posts.show', $post->slug),
        'author'        => [
            '@type' => 'Person',
            'name'  => $post->user->name,
            'url'   => route('author.show', $post->user),
        ],
        'publisher' => [
            '@type' => 'Person',
            'name'  => $post->user->name,
        ],
    ];
    if ($post->cover_image) {
        $jsonLd['image'] = secure_asset('storage/' . $post->cover_image);
    }
    echo json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    @endphp
    </script>
@endpush

<div>

@if(! $post->isPublished() && auth()->id() === $post->user_id)
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-xl px-5 py-4 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3 text-sm text-yellow-800 dark:text-yellow-300">
            <span class="text-xl">📝</span>
            <span><strong>Pré-visualização</strong> — este artigo ainda está como rascunho e não aparece no blog.</span>
        </div>
        <a href="{{ route('posts.edit', $post) }}"
           class="shrink-0 text-sm font-semibold text-yellow-700 dark:text-yellow-400 hover:underline">
            Editar / Publicar →
        </a>
    </div>
</div>
@endif

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 grid grid-cols-1 lg:grid-cols-[1fr_18rem] gap-12 items-start">
    
    <!-- Conteúdo Principal -->
    <div class="min-w-0 w-full">
        <article>
            <!-- Título -->
            <header class="mb-12 text-center lg:text-left">
                <div class="mb-4 flex flex-wrap items-center justify-center lg:justify-start gap-2">
                    @if($post->category)
                        <a href="{{ route('categories.show', $post->category->slug) }}" wire:navigate
                           class="inline-flex items-center rounded-full bg-indigo-50 dark:bg-indigo-900/30 px-3 py-1 text-xs font-semibold text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-800 transition-colors">
                            {{ $post->category->name }}
                        </a>
                    @else
                        <span class="inline-flex items-center rounded-full bg-blue-50 dark:bg-blue-900/30 px-3 py-1 text-xs font-medium text-blue-700 dark:text-blue-300">
                            Artigo
                        </span>
                    @endif
                    @foreach($post->tags as $tag)
                        <a href="{{ route('tags.show', $tag->slug) }}" wire:navigate
                           class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-3 py-1 text-xs text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                            #{{ $tag->name }}
                        </a>
                    @endforeach
                </div>
                <h1 class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-3xl lg:text-3xl mb-6">
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
                    <x-social-links :user="$post->user" size="sm" />
                    <span>&bull;</span>
                    <time>{{ ($post->published_at ?? $post->created_at)->format('d/m/Y') }}</time>
                    <span>&bull;</span>
                    <span>{{ $post->reading_time }} min de leitura</span>
                </div>
            </header>

            <!-- Imagem de Capa -->
            @if($post->cover_image)
                <div class="mb-12">
                    <img src="{{ asset('storage/' . $post->cover_image) }}" alt="{{ $post->title }}" class="w-full rounded-2xl shadow-xl object-cover max-h-[500px]">
                </div>
            @endif

            <!-- Conteúdo do Artigo -->
            <style>
                #article-content h1 { font-size: 1.5rem; line-height: 2rem; font-weight: 700; margin-top: 2rem; margin-bottom: 1rem; color: inherit; }
                #article-content h2 { font-size: 1.25rem; line-height: 1.75rem; font-weight: 700; margin-top: 1.5rem; margin-bottom: 0.75rem; color: inherit; }
                #article-content h3 { font-size: 1.125rem; line-height: 1.5rem; font-weight: 700; margin-top: 1.25rem; margin-bottom: 0.5rem; color: inherit; }
                #article-content strong { color: inherit; font-weight: 700; }
                #article-content p { margin-bottom: 1.5rem; }
            </style>
            <div id="article-content" class="trix-content text-gray-800 dark:text-gray-200 leading-relaxed text-base sm:text-lg selection:bg-blue-100 dark:selection:bg-blue-900">
                @php
                    $contentWithVideos = preg_replace_callback(
                        '/\[\[video:(https?:\/\/[^\]\s]+)\]\]/i',
                        function ($m) {
                            $videoUrl = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
                            return '<figure class="article-video"><video controls preload="metadata" playsinline><source src="' . $videoUrl . '">Seu navegador não suporta vídeo HTML5.</video></figure>';
                        },
                        $post->content
                    );

                    $renderedContent = preg_replace_callback(
                        '/<a(\s[^>]*)href="(https?:\/\/[^"]+)"([^>]*)>([\s\S]*?)<\/a>/i',
                        function ($m) {
                            $innerText = trim(strip_tags($m[4]));
                            $href = $m[2];
                            $needsLabel = preg_match('#^https?://#i', $innerText);
                            $label = $needsLabel ? ' aria-label="' . htmlspecialchars(parse_url($href, PHP_URL_HOST) ?: $href) . '"' : '';
                            return '<a' . $m[1] . 'href="' . $href . '"' . $m[3] . $label . '>' . $m[4] . '</a>';
                        },
                        $contentWithVideos
                    );
                @endphp
                {!! $renderedContent !!}
            </div>
        </article>

        <!-- Footer do Artigo -->
        <div class="mt-20 pt-10 border-t border-gray-100 dark:border-gray-800">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-6 mb-12">
                <a href="/" class="text-sm font-semibold text-blue-600 dark:text-blue-400 hover:underline">&larr; Voltar para a página inicial</a>
            </div>

            <!-- Comentário da IA -->
            @if($post->latestAiComment)
                @php
                    $aiComment = $post->latestAiComment;
                    $aiUser = $post->user;
                    $hex = ltrim($aiUser->gemini_accent_color ?? '#7c3aed', '#');
                    [$r, $g, $b] = array_map('hexdec', str_split($hex, 2));
                    $bgStyle     = "rgba($r,$g,$b,0.07)";
                    $borderStyle = "rgba($r,$g,$b,0.35)";
                    $accentColor = '#' . $hex;
                    $aiName      = $aiUser->gemini_ai_name ?: 'BOT Sarcástico';
                    $aiPhoto     = $aiUser->gemini_ai_photo ? asset('storage/' . $aiUser->gemini_ai_photo) : '';
                @endphp
                <div id="ai-comment-section"
                     data-ai-name="{{ $aiName }}"
                     data-ai-photo="{{ $aiPhoto }}"
                     data-ai-accent="{{ $accentColor }}"
                     style="background-color:{{ $bgStyle }};border-color:{{ $borderStyle }};scroll-margin-top:6rem"
                     class="mb-12 rounded-2xl border p-6">
                    <div class="flex items-center gap-3 mb-4">
                        @if($aiUser->gemini_ai_photo)
                            <img src="{{ asset('storage/' . $aiUser->gemini_ai_photo) }}" class="w-10 h-10 rounded-full object-cover" alt="{{ $aiUser->gemini_ai_name }}">
                        @else
                            <div class="w-10 h-10 rounded-full bg-purple-200 dark:bg-purple-800 flex items-center justify-center text-xl">🤖</div>
                        @endif
                        <div>
                            <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $aiUser->gemini_ai_name ?: 'BOT Sarcástico' }}</p>
                            <p class="text-xs text-gray-400">Opinião não solicitada &bull; <span class="italic">powered by {{ $aiComment->model }}</span></p>
                        </div>
                    </div>
                    <div class="text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-wrap">{{ $aiComment->content }}</div>
                </div>
            @endif

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

    <!-- Barra Lateral (Sumário Dinâmico) -->
    <aside class="hidden lg:block sticky top-28 self-start z-20 w-72">
        <div class="max-h-[calc(100vh-10rem)] overflow-y-auto pr-4 scrollbar-thin scrollbar-thumb-gray-200 dark:scrollbar-thumb-gray-800">
            <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-6 flex items-center gap-2">
                <span class="w-8 h-px bg-gray-200 dark:bg-gray-800"></span>
                Nesta Página
            </p>
            <nav aria-label="Índice do artigo">
                <ul id="toc-list" class="space-y-4 text-xs font-medium text-gray-500 dark:text-gray-400 border-l border-gray-100 dark:border-gray-800">
                    <!-- Gerado via JavaScript -->
                </ul>
            </nav>
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

                // Mesmo sem seções, mostra o link da IA se existir
                const aiSection = document.getElementById('ai-comment-section');
                if (aiSection) appendAiTocItem();
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
                li.className = '-ml-px border-l border-transparent hover:border-blue-500 transition-colors';
                
                const a = document.createElement('a');
                a.href = location.pathname + '#' + id;
                a.dataset.tocId = id;
                a.innerText = heading.innerText;
                a.className = 'pl-4 hover:text-blue-600 dark:hover:text-blue-400 transition-colors block line-clamp-2 leading-tight py-0.5';
                
                // Aplica indentação adicional dependendo se é H2 ou H3
                if (heading.tagName === 'H2') {
                    a.classList.add('pl-6');
                } else if (heading.tagName === 'H3') {
                    a.classList.add('pl-8');
                }

                li.appendChild(a);
                tocList.appendChild(li);
            });

            // Adiciona link para o comentário da IA no final do sumário
            function appendAiTocItem() {
                const aiSection = document.getElementById('ai-comment-section');
                const aiName    = aiSection.dataset.aiName  || '🤖 Opinião da IA';
                const aiPhoto   = aiSection.dataset.aiPhoto || '';
                const aiAccent  = aiSection.dataset.aiAccent || '#7c3aed';

                const divider = document.createElement('li');
                divider.className = 'border-t border-gray-100 dark:border-gray-800 pt-3 mt-3 -ml-px';

                const a = document.createElement('a');
                a.href = location.pathname + '#ai-comment-section';
                a.style.color = aiAccent;
                a.className = 'pl-4 hover:opacity-75 transition-opacity flex items-center gap-2 leading-tight py-0.5 font-semibold';

                if (aiPhoto) {
                    const img = document.createElement('img');
                    img.src = aiPhoto;
                    img.className = 'w-5 h-5 rounded-full object-cover shrink-0';
                    img.alt = aiName;
                    a.appendChild(img);
                } else {
                    const emoji = document.createElement('span');
                    emoji.textContent = '🤖';
                    a.appendChild(emoji);
                }

                const nameSpan = document.createElement('span');
                nameSpan.textContent = aiName;
                nameSpan.className = 'line-clamp-1';
                a.appendChild(nameSpan);

                divider.appendChild(a);
                tocList.appendChild(divider);
            }

            if (document.getElementById('ai-comment-section')) {
                appendAiTocItem();
            }

            // Voltar ao topo
            const backLi = document.createElement('li');
            backLi.className = 'border-t border-gray-100 dark:border-gray-800 pt-3 mt-3 -ml-px';
            const backA = document.createElement('a');
            backA.href = '#';
            backA.className = 'pl-4 flex items-center gap-1.5 text-gray-400 dark:text-gray-500 hover:text-blue-600 dark:hover:text-blue-400 transition-colors leading-tight py-0.5';
            backA.innerHTML = '<span aria-hidden="true">↑</span><span>Voltar ao topo</span>';
            backA.addEventListener('click', e => { e.preventDefault(); window.scrollTo({ top: 0, behavior: 'smooth' }); });
            backLi.appendChild(backA);
            tocList.appendChild(backLi);

            // Destaca seção ativa conforme scroll
            function setActive(id) {
                tocList.querySelectorAll('a[data-toc-id]').forEach(a => {
                    const active = a.dataset.tocId === id;
                    a.classList.toggle('font-bold', active);
                    a.classList.toggle('text-gray-900', active);
                    a.classList.toggle('dark:text-white', active);
                });
            }

            const observer = new IntersectionObserver(
                entries => entries.forEach(e => { if (e.isIntersecting) setActive(e.target.id); }),
                { rootMargin: '0px 0px -70% 0px', threshold: 0 }
            );
            headings.forEach(h => { if (h.id) observer.observe(h); });
        });
    </script>

    <style>
        /* Estilos Semânticos para o Conteúdo do Artigo (Trix) */
        .trix-content h1 { font-size: 2.5rem; font-weight: 800; margin: 2rem 0 1rem; color: var(--tw-prose-headings); scroll-margin-top: 6rem; }
        .trix-content h2 { font-size: 1.875rem; font-weight: 700; margin: 1.5rem 0 0.75rem; scroll-margin-top: 6rem; }
        .trix-content h3 { font-size: 1.5rem; font-weight: 600; margin: 1.25rem 0 0.5rem; scroll-margin-top: 6rem; }
        #ai-comment-section { scroll-margin-top: 6rem; }
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
        .trix-content .article-video { margin: 2rem 0; }
        .trix-content .article-video video { width: 100%; height: auto; border-radius: 1rem; background: #000; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
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
</div>
