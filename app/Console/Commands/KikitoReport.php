<?php

namespace App\Console\Commands;

use App\Models\PageView;
use App\Models\Post;
use App\Models\User;
use App\Services\AiServiceFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class KikitoReport extends Command
{
    protected $signature = 'app:kikito-report {--email= : Destinatário (padrão: MAIL_FROM_ADDRESS)}';

    protected $description = 'Gera relatório semanal de tráfego com comentário sarcástico do Kikito e envia por e-mail';

    public function handle(AiServiceFactory $factory): int
    {
        $user = User::first();

        if (! $user || $user->aiProviders()->doesntExist()) {
            $this->error('Nenhum usuário com AI provider configurado.');
            return Command::FAILURE;
        }

        $stats      = $this->collectStats();
        $commentary = $this->generateCommentary($stats, $user, $factory);
        $this->sendEmail($stats, $commentary);

        $this->info('Relatório do Kikito enviado com sucesso!');
        return Command::SUCCESS;
    }

    private function collectStats(): array
    {
        $since7  = now()->subDays(7);
        $since30 = now()->subDays(30);

        $views7d  = PageView::where('created_at', '>=', $since7)->count();
        $views30d = PageView::where('created_at', '>=', $since30)->count();

        $unique7d = PageView::where('created_at', '>=', $since7)
            ->distinct('ip_hash')->count('ip_hash');

        $topPages = PageView::where('created_at', '>=', $since7)
            ->selectRaw('path, count(*) as total')
            ->groupBy('path')
            ->orderByDesc('total')
            ->limit(5)
            ->pluck('total', 'path')
            ->toArray();

        $topReferrers = PageView::where('created_at', '>=', $since7)
            ->whereNotNull('referrer')
            ->selectRaw('referrer, count(*) as total')
            ->groupBy('referrer')
            ->orderByDesc('total')
            ->limit(5)
            ->pluck('total', 'referrer')
            ->toArray();

        $devices = PageView::where('created_at', '>=', $since7)
            ->selectRaw('device, count(*) as total')
            ->groupBy('device')
            ->orderByDesc('total')
            ->pluck('total', 'device')
            ->toArray();

        $totalPosts = Post::whereNotNull('published_at')->count();
        $totalPostViews = Post::sum('views_count');

        return compact(
            'views7d', 'views30d', 'unique7d',
            'topPages', 'topReferrers', 'devices',
            'totalPosts', 'totalPostViews'
        );
    }

    private function generateCommentary(array $stats, User $user, AiServiceFactory $factory): string
    {
        try {
            $service = $factory->for($user);
        } catch (\Throwable) {
            return 'O Kikito estava com preguiça essa semana e não quis comentar.';
        }

        $topPagesText = collect($stats['topPages'])
            ->map(fn ($v, $k) => "  - /{$k}: {$v} views")
            ->join("\n");

        $topRefText = collect($stats['topReferrers'])
            ->map(fn ($v, $k) => "  - {$k}: {$v} visitas")
            ->join("\n") ?: '  (nenhum referrer externo)';

        $devicesText = collect($stats['devices'])
            ->map(fn ($v, $k) => "  - {$k}: {$v}")
            ->join("\n");

        $prompt = <<<PROMPT
Você é um crítico sarcástico e bem-humorado chamado Kikito. Comente de forma irônica mas sem ser ofensivo.

Você recebeu os dados de tráfego semanal do blog. Escreva um comentário curto (máximo 3 parágrafos) analisando esses números com sua persona. Seja específico sobre os dados.

DADOS DA SEMANA:
- Visualizações de página: {$stats['views7d']}
- Visitantes únicos (aproximado): {$stats['unique7d']}
- Visualizações do mês: {$stats['views30d']}
- Total de posts publicados: {$stats['totalPosts']}
- Total histórico de views nos posts: {$stats['totalPostViews']}

Top páginas:
{$topPagesText}

Top referrers:
{$topRefText}

Dispositivos:
{$devicesText}
PROMPT;

        try {
            return $service->generateText($prompt);
        } catch (\Throwable) {
            return 'O Kikito estava com preguiça essa semana e não quis comentar.';
        }
    }

    private function sendEmail(array $stats, string $commentary): void
    {
        $to = $this->option('email') ?: config('mail.from.address');

        $topPagesHtml = collect($stats['topPages'])
            ->map(fn ($v, $k) => "<li><code>/{$k}</code> — {$v} views</li>")
            ->join('');

        $topRefHtml = collect($stats['topReferrers'])
            ->map(fn ($v, $k) => "<li>{$k} — {$v}</li>")
            ->join('') ?: '<li>Nenhum referrer externo</li>';

        $devicesHtml = collect($stats['devices'])
            ->map(fn ($v, $k) => "<li>{$k}: {$v}</li>")
            ->join('');

        $commentaryHtml = nl2br(e($commentary));
        $date = now()->format('d/m/Y');

        $html = <<<HTML
        <h2>Relatório do Kikito — semana até {$date}</h2>

        <h3>Resumo da semana</h3>
        <ul>
            <li>Visualizações: <strong>{$stats['views7d']}</strong></li>
            <li>Visitantes únicos: <strong>{$stats['unique7d']}</strong></li>
            <li>Views no mês: <strong>{$stats['views30d']}</strong></li>
        </ul>

        <h3>Top páginas</h3>
        <ul>{$topPagesHtml}</ul>

        <h3>Top referrers</h3>
        <ul>{$topRefHtml}</ul>

        <h3>Dispositivos</h3>
        <ul>{$devicesHtml}</ul>

        <h3>Comentário do Kikito</h3>
        <p>{$commentaryHtml}</p>
        HTML;

        Mail::html($html, function ($message) use ($to, $date) {
            $message->to($to)->subject("📊 Relatório Kikito — {$date}");
        });
    }
}
