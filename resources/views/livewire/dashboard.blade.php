<?php

use Livewire\Volt\Component;
use App\Models\PageView;
use App\Models\Post;
use Illuminate\Support\Facades\Artisan;

new class extends Component {
    public int $totalPosts = 0;
    public int $totalViews = 0;
    public $topPosts;

    // Totais 7d
    public int $views7d      = 0;
    public int $humanViews7d = 0;
    public int $botViews7d   = 0;
    public int $unique7d     = 0;
    public int $views30d     = 0;

    // Listas 7d
    public array $topPages     = [];
    public array $topReferrers = [];
    public array $devices      = [];

    // Engajamento 30d
    public int   $avgTimeOnPage  = 0;
    public int   $avgScrollDepth = 0;
    public array $topByTime      = [];
    public array $topByFullRead  = [];

    // R2 sync
    public bool   $syncing    = false;
    public string $syncLog    = '';
    public string $syncStatus = '';

    public function mount(): void
    {
        $this->totalPosts = Post::where('user_id', auth()->id())->count();
        $this->totalViews = Post::where('user_id', auth()->id())->sum('views_count');
        $this->topPosts   = Post::where('user_id', auth()->id())
            ->orderByDesc('views_count')
            ->limit(5)
            ->get(['id', 'title', 'slug', 'views_count', 'created_at']);

        $since7  = now()->subDays(7);
        $since30 = now()->subDays(30);

        $this->views7d      = PageView::where('created_at', '>=', $since7)->count();
        $this->humanViews7d = PageView::where('created_at', '>=', $since7)->where('is_bot', false)->count();
        $this->botViews7d   = $this->views7d - $this->humanViews7d;
        $this->views30d     = PageView::where('created_at', '>=', $since30)->count();

        $this->unique7d = PageView::where('created_at', '>=', $since7)
            ->where('is_bot', false)
            ->distinct('ip_hash')
            ->count('ip_hash');

        $this->topPages = PageView::where('created_at', '>=', $since7)
            ->where('is_bot', false)
            ->selectRaw('path, count(*) as total')
            ->groupBy('path')
            ->orderByDesc('total')
            ->limit(5)
            ->pluck('total', 'path')
            ->toArray();

        $this->topReferrers = PageView::where('created_at', '>=', $since7)
            ->where('is_bot', false)
            ->whereNotNull('referrer')
            ->selectRaw('referrer, count(*) as total')
            ->groupBy('referrer')
            ->orderByDesc('total')
            ->limit(5)
            ->pluck('total', 'referrer')
            ->toArray();

        $this->devices = PageView::where('created_at', '>=', $since7)
            ->where('is_bot', false)
            ->selectRaw('device, count(*) as total')
            ->groupBy('device')
            ->orderByDesc('total')
            ->pluck('total', 'device')
            ->toArray();

        $this->avgTimeOnPage = (int) round(
            PageView::where('created_at', '>=', $since30)
                ->where('is_bot', false)
                ->whereNotNull('time_on_page')
                ->avg('time_on_page') ?? 0
        );

        $this->avgScrollDepth = (int) round(
            PageView::where('created_at', '>=', $since30)
                ->where('is_bot', false)
                ->whereNotNull('scroll_depth')
                ->avg('scroll_depth') ?? 0
        );

        $this->topByTime = PageView::where('created_at', '>=', $since30)
            ->where('is_bot', false)
            ->whereNotNull('time_on_page')
            ->selectRaw('path, round(avg(time_on_page)) as avg_time, count(*) as sessions')
            ->groupBy('path')
            ->havingRaw('count(*) >= 2')
            ->orderByDesc('avg_time')
            ->limit(5)
            ->get(['path', 'avg_time', 'sessions'])
            ->toArray();

        $this->topByFullRead = PageView::where('created_at', '>=', $since30)
            ->where('is_bot', false)
            ->where('scroll_depth', '>=', 100)
            ->selectRaw('path, count(*) as full_reads')
            ->groupBy('path')
            ->orderByDesc('full_reads')
            ->limit(5)
            ->get(['path', 'full_reads'])
            ->toArray();
    }

    public function syncToR2(): void
    {
        $this->syncing    = true;
        $this->syncLog    = '';
        $this->syncStatus = '';

        try {
            Artisan::call('media:sync-to-r2', ['--force' => true]);
            $this->syncLog    = Artisan::output();
            $this->syncStatus = 'success';
        } catch (\Throwable $e) {
            $this->syncLog    = $e->getMessage();
            $this->syncStatus = 'error';
        }

        $this->syncing = false;
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            <!-- Cards de Estatísticas -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-2xl shrink-0">📝</div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total de Posts</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $totalPosts }}</p>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center text-2xl shrink-0">👁️</div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total de Visualizações</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($totalViews, 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>

            <!-- Analytics Soberano -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">📊 Analytics — últimos 7 dias</h3>
                    @if($views7d > 0)
                    @php $botPercent = round($botViews7d / $views7d * 100); @endphp
                    <span class="text-xs px-2.5 py-1 rounded-full font-semibold
                        {{ $botPercent > 70
                            ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300'
                            : ($botPercent > 40
                                ? 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300'
                                : 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300') }}">
                        🤖 {{ $botPercent }}% bots
                    </span>
                    @endif
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-gray-100 dark:divide-gray-700">
                    <div class="px-6 py-5 text-center">
                        <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($humanViews7d, 0, ',', '.') }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Views humanas</p>
                    </div>
                    <div class="px-6 py-5 text-center">
                        <p class="text-3xl font-bold text-purple-600 dark:text-purple-400">{{ number_format($unique7d, 0, ',', '.') }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Visitantes únicos</p>
                    </div>
                    <div class="px-6 py-5 text-center">
                        <p class="text-3xl font-bold text-green-600 dark:text-green-400">{{ number_format($views30d, 0, ',', '.') }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Views no mês (total)</p>
                    </div>
                    <div class="px-6 py-5 text-center">
                        <p class="text-3xl font-bold text-gray-400 dark:text-gray-500">{{ number_format($botViews7d, 0, ',', '.') }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Requests de bots</p>
                    </div>
                </div>

                @if(count($topPages) > 0 || count($topReferrers) > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-gray-100 dark:divide-gray-700 border-t border-gray-100 dark:border-gray-700">
                    <div class="px-6 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-3">Top Páginas (humanos)</p>
                        @forelse($topPages as $path => $count)
                            <div class="flex justify-between items-center py-1.5">
                                <span class="text-sm text-gray-700 dark:text-gray-300 truncate font-mono">/{{ $path }}</span>
                                <span class="ml-2 shrink-0 text-sm font-semibold text-blue-600 dark:text-blue-400">{{ number_format($count, 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400 italic">Sem dados</p>
                        @endforelse
                    </div>

                    <div class="px-6 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-3">Top Referrers</p>
                        @forelse($topReferrers as $ref => $count)
                            <div class="flex justify-between items-center py-1.5">
                                <span class="text-sm text-gray-700 dark:text-gray-300 truncate">{{ $ref }}</span>
                                <span class="ml-2 shrink-0 text-sm font-semibold text-purple-600 dark:text-purple-400">{{ number_format($count, 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400 italic">Sem referrers externos</p>
                        @endforelse
                    </div>
                </div>

                @if(count($devices) > 0)
                <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 flex flex-wrap gap-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 self-center">Dispositivos:</p>
                    @foreach($devices as $device => $count)
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            {{ match($device) { 'desktop' => '🖥️', 'mobile' => '📱', 'tablet' => '📟', default => '❓' } }}
                            {{ ucfirst($device) }}: <strong>{{ $count }}</strong>
                        </span>
                    @endforeach
                </div>
                @endif
                @endif
            </div>

            <!-- Engajamento (30 dias) -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">⏱️ Engajamento — últimos 30 dias</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Apenas visitantes humanos com dados de scroll e tempo.</p>
                </div>

                <div class="grid grid-cols-2 divide-x divide-gray-100 dark:divide-gray-700 border-b border-gray-100 dark:border-gray-700">
                    <div class="px-6 py-5 text-center">
                        @php
                            $m = intdiv($avgTimeOnPage, 60);
                            $s = $avgTimeOnPage % 60;
                            $timeStr = $avgTimeOnPage > 0
                                ? ($m > 0 ? "{$m}m " . ($s > 0 ? "{$s}s" : '') : "{$s}s")
                                : '—';
                        @endphp
                        <p class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">{{ trim($timeStr) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Tempo médio na página</p>
                    </div>
                    <div class="px-6 py-5 text-center">
                        <p class="text-3xl font-bold text-teal-600 dark:text-teal-400">{{ $avgScrollDepth > 0 ? $avgScrollDepth . '%' : '—' }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Scroll médio</p>
                    </div>
                </div>

                @if(count($topByTime) > 0 || count($topByFullRead) > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-gray-100 dark:divide-gray-700">
                    <!-- Top por tempo de leitura -->
                    <div class="px-6 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-3">Mais lidos (tempo médio)</p>
                        @forelse($topByTime as $row)
                            @php
                                $avgSec = (int) $row['avg_time'];
                                $mm = intdiv($avgSec, 60);
                                $ss = $avgSec % 60;
                                $label = $mm > 0 ? "{$mm}m" . ($ss > 0 ? " {$ss}s" : '') : "{$ss}s";
                                $slug = preg_replace('#^blog/#', '', $row['path']);
                            @endphp
                            <div class="flex justify-between items-center py-1.5 gap-2">
                                <span class="text-sm text-gray-700 dark:text-gray-300 truncate font-mono">{{ $slug }}</span>
                                <span class="shrink-0 text-sm font-semibold text-indigo-600 dark:text-indigo-400">{{ $label }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400 italic">Dados insuficientes</p>
                        @endforelse
                    </div>

                    <!-- Top por leitura completa (scroll 100%) -->
                    <div class="px-6 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-3">Lidos até o fim (scroll 100%)</p>
                        @forelse($topByFullRead as $row)
                            @php $slug = preg_replace('#^blog/#', '', $row['path']); @endphp
                            <div class="flex justify-between items-center py-1.5 gap-2">
                                <span class="text-sm text-gray-700 dark:text-gray-300 truncate font-mono">{{ $slug }}</span>
                                <span class="shrink-0 text-sm font-semibold text-teal-600 dark:text-teal-400">{{ $row['full_reads'] }}×</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400 italic">Dados insuficientes</p>
                        @endforelse
                    </div>
                </div>
                @else
                    <p class="px-6 py-5 text-sm text-gray-400 italic text-center">
                        Dados de engajamento aparecem aqui após os primeiros acessos reais.
                    </p>
                @endif
            </div>

            <!-- Posts Mais Visualizados -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">🏆 Posts Mais Visualizados</h3>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($topPosts as $post)
                        <div class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('posts.show', $post->slug) }}" target="_blank"
                                   class="text-sm font-medium text-gray-900 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400 truncate block">
                                    {{ $post->title }}
                                </a>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $post->created_at->format('d/m/Y') }}</p>
                            </div>
                            <div class="ml-4 shrink-0 flex items-center gap-1.5 text-sm font-semibold text-green-600 dark:text-green-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                {{ number_format($post->views_count, 0, ',', '.') }}
                            </div>
                        </div>
                    @empty
                        <p class="px-6 py-8 text-sm text-gray-400 text-center italic">Nenhum post ainda. <a href="{{ route('posts.create') }}" class="text-blue-500 hover:underline">Crie o primeiro!</a></p>
                    @endforelse
                </div>
            </div>

            <!-- Sincronização R2 -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between gap-4">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">☁️ Cloudflare R2 — Sincronizar Assets</h3>
                        <p class="text-xs text-gray-400 mt-0.5">
                            Envia todas as imagens e vídeos locais para o R2. Não regenera nada, apenas copia.
                            @if(config('filesystems.image_disk') === 'r2')
                                <span class="ml-2 text-green-600 dark:text-green-400 font-semibold">● R2 ativo</span>
                            @else
                                <span class="ml-2 text-yellow-600 dark:text-yellow-400 font-semibold">● Disco local ativo (IMAGE_DISK=public)</span>
                            @endif
                        </p>
                    </div>
                    <button wire:click="syncToR2"
                            wire:loading.attr="disabled"
                            @disabled($syncing)
                            class="shrink-0 inline-flex items-center gap-2 px-4 py-2 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white text-sm font-semibold rounded-lg transition-colors">
                        <span wire:loading.remove wire:target="syncToR2">Sincronizar para R2</span>
                        <span wire:loading wire:target="syncToR2" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Enviando...
                        </span>
                    </button>
                </div>

                @if($syncLog)
                    <div class="px-6 py-4">
                        <div class="rounded-lg p-4 font-mono text-xs whitespace-pre-wrap leading-relaxed
                                    {{ $syncStatus === 'success'
                                        ? 'bg-green-50 dark:bg-green-950/30 text-green-800 dark:text-green-300 border border-green-200 dark:border-green-800'
                                        : 'bg-red-50 dark:bg-red-950/30 text-red-800 dark:text-red-300 border border-red-200 dark:border-red-800' }}">
                            {{ $syncLog }}
                        </div>
                    </div>
                @endif
            </div>

        </div>
    </div>
</div>
