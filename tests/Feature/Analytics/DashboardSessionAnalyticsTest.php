<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsSession;
use App\Models\PageView;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardSessionAnalyticsTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function dashboard_agrega_aquisicao_landings_e_fluxo_por_sessao(): void
    {
        $user = User::factory()->create();
        $visitorId = (string) Str::uuid();
        $facebook = $this->createAnalyticsSession($visitorId, 'facebook', 'blog/calvino');
        $direct = $this->createAnalyticsSession((string) Str::uuid(), 'direct', '/');

        $this->recordView($facebook, '/', now()->subMinutes(5));
        $this->recordView($facebook, 'blog/calvino', now()->subMinutes(4));
        $this->recordView($direct, '/', now()->subMinutes(3));

        $component = Volt::actingAs($user)->test('dashboard');

        $this->assertSame(2, $component->get('visitors7d'));
        $this->assertSame(2, $component->get('sessions7d'));
        $this->assertSame(3, $component->get('sessionPageViews7d'));
        $this->assertSame(1.5, $component->get('pagesPerSession7d'));
        $this->assertSame(1, $component->get('sessionSources')['facebook']);
        $this->assertSame('blog/calvino', $component->get('internalFlows')[0]['to']);
    }

    private function createAnalyticsSession(string $visitorId, string $source, string $landing): AnalyticsSession
    {
        return AnalyticsSession::create([
            'id' => (string) Str::uuid(),
            'visitor_id' => $visitorId,
            'started_at' => now()->subMinutes(10),
            'last_seen_at' => now(),
            'landing_path' => $landing,
            'landing_url' => 'https://example.com/'.ltrim($landing, '/'),
            'source_key' => $source,
            'device' => 'desktop',
            'ip_hash' => hash('sha256', $visitorId),
            'is_bot' => false,
        ]);
    }

    private function recordView(AnalyticsSession $session, string $path, $createdAt): void
    {
        PageView::create([
            'session_id' => $session->id,
            'path' => $path,
            'device' => 'desktop',
            'ip_hash' => $session->ip_hash,
            'view_token' => (string) Str::uuid(),
            'is_bot' => false,
            'created_at' => $createdAt,
        ]);
    }
}
