<?php

namespace Tests\Feature\Analytics;

use App\Models\PageView;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnalyticsEngageTest extends TestCase
{
    use DatabaseTransactions;

    private function makePageView(array $attrs = []): PageView
    {
        return PageView::create(array_merge([
            'path'       => 'blog/meu-post',
            'referrer'   => null,
            'device'     => 'desktop',
            'ip_hash'    => hash('sha256', '127.0.0.1' . config('app.key')),
            'user_agent' => 'Mozilla/5.0',
            'view_token' => '00000000-0000-0000-0000-000000000099',
            'is_bot'     => false,
            'created_at' => now(),
        ], $attrs));
    }

    #[Test]
    public function engage_atualiza_pageview_com_dados_de_comportamento(): void
    {
        $this->makePageView();

        $this->postJson('/analytics/engage', [
            'path'         => 'blog/meu-post',
            'scroll_depth' => 75,
            'time_on_page' => 120,
            'language'     => 'pt-BR',
            'timezone'     => 'America/Sao_Paulo',
            'screen_width' => 1440,
        ])->assertNoContent();

        $this->assertDatabaseHas('page_views', [
            'path'         => 'blog/meu-post',
            'scroll_depth' => 75,
            'time_on_page' => 120,
            'language'     => 'pt-BR',
        ]);
    }

    #[Test]
    public function engage_nao_atualiza_pageview_de_bot(): void
    {
        $this->makePageView(['is_bot' => true]);

        $this->postJson('/analytics/engage', [
            'path'         => 'blog/meu-post',
            'scroll_depth' => 100,
            'time_on_page' => 60,
        ])->assertNoContent();

        $this->assertDatabaseMissing('page_views', [
            'path'         => 'blog/meu-post',
            'scroll_depth' => 100,
        ]);
    }

    #[Test]
    public function engage_nao_atualiza_pageview_muito_antiga(): void
    {
        $this->makePageView(['created_at' => now()->subHours(3)]);

        $this->postJson('/analytics/engage', [
            'path'         => 'blog/meu-post',
            'scroll_depth' => 50,
            'time_on_page' => 90,
        ])->assertNoContent();

        $this->assertDatabaseMissing('page_views', [
            'path'         => 'blog/meu-post',
            'scroll_depth' => 50,
        ]);
    }

    #[Test]
    public function engage_rejeita_dados_invalidos(): void
    {
        $this->postJson('/analytics/engage', [
            'path'         => '',
            'scroll_depth' => 150,
            'time_on_page' => -1,
        ])->assertUnprocessable();
    }
}
