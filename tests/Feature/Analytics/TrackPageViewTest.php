<?php

namespace Tests\Feature\Analytics;

use App\Jobs\RecordPageViewJob;
use App\Models\AnalyticsSession;
use App\Models\Post;
use App\Models\User;
use App\Services\Analytics\SessionTracker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Spatie\ResponseCache\Facades\ResponseCache;
use Tests\TestCase;

class TrackPageViewTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        ResponseCache::clear();
        Route::get('/analytics-test-page', fn () => response('<html>ok</html>'))->middleware('web');
    }

    protected function tearDown(): void
    {
        ResponseCache::clear();
        parent::tearDown();
    }

    #[Test]
    public function visita_publica_dispara_job_de_registro(): void
    {
        Queue::fake();

        $this->get('/analytics-test-page');

        Queue::assertPushed(RecordPageViewJob::class);
    }

    #[Test]
    public function usuario_autenticado_nao_dispara_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user)->get('/analytics-test-page');

        Queue::assertNotPushed(RecordPageViewJob::class);
    }

    #[Test]
    public function bot_dispara_job_marcado_como_bot(): void
    {
        Queue::fake();

        $this->withHeaders(['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])
            ->get('/analytics-test-page');

        Queue::assertPushed(RecordPageViewJob::class, function (RecordPageViewJob $job) {
            return $job->isBot === true;
        });
    }

    #[Test]
    public function visita_humana_dispara_job_sem_flag_bot(): void
    {
        Queue::fake();

        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'])
            ->get('/analytics-test-page');

        Queue::assertPushed(RecordPageViewJob::class, function (RecordPageViewJob $job) {
            return $job->isBot === false;
        });
    }

    #[Test]
    public function paths_ignorados_nao_disparam_job(): void
    {
        Queue::fake();

        $this->post('/login', ['email' => 'a@a.com', 'password' => '123']);
        $this->get('/wp-admin');
        $this->get('/xmlrpc');

        Queue::assertNotPushed(RecordPageViewJob::class);
    }

    #[Test]
    public function job_salva_pageview_no_banco(): void
    {
        $post = Post::factory()->published()->create();

        (new RecordPageViewJob(
            sessionId: null,
            path: "blog/{$post->slug}",
            referrer: 'google.com',
            pageReferrer: 'https://google.com/search',
            pageReferrerDomain: 'google.com',
            device: 'desktop',
            ipHash: hash('sha256', '127.0.0.1'.config('app.key')),
            userAgent: 'Mozilla/5.0',
            viewToken: '00000000-0000-0000-0000-000000000001',
            isBot: false,
        ))->handle();

        $this->assertDatabaseHas('page_views', [
            'path' => "blog/{$post->slug}",
            'referrer' => 'google.com',
            'device' => 'desktop',
            'is_bot' => false,
        ]);
    }

    #[Test]
    public function job_salva_bot_com_flag_correta(): void
    {
        (new RecordPageViewJob(
            sessionId: null,
            path: 'blog/algum-post',
            referrer: null,
            pageReferrer: null,
            pageReferrerDomain: null,
            device: 'desktop',
            ipHash: hash('sha256', '10.0.0.1'.config('app.key')),
            userAgent: 'Googlebot/2.1',
            viewToken: '00000000-0000-0000-0000-000000000002',
            isBot: true,
        ))->handle();

        $this->assertDatabaseHas('page_views', [
            'path' => 'blog/algum-post',
            'is_bot' => true,
        ]);
    }

    #[Test]
    public function detects_mobile_user_agent(): void
    {
        Queue::fake();

        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) Mobile/15E148'])
            ->get('/analytics-test-page');

        Queue::assertPushed(RecordPageViewJob::class, function (RecordPageViewJob $job) {
            return $job->device === 'mobile';
        });
    }

    #[Test]
    public function detects_tablet_user_agent(): void
    {
        Queue::fake();

        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPad; CPU OS 14_0 like Mac OS X)'])
            ->get('/analytics-test-page');

        Queue::assertPushed(RecordPageViewJob::class, function (RecordPageViewJob $job) {
            return $job->device === 'tablet';
        });
    }

    #[Test]
    public function extrai_host_do_referrer(): void
    {
        Queue::fake();

        $this->withHeaders(['Referer' => 'https://google.com/search?q=blog'])
            ->get('/analytics-test-page');

        Queue::assertPushed(RecordPageViewJob::class, function (RecordPageViewJob $job) {
            return $job->referrer === 'google.com';
        });
    }

    #[Test]
    public function ignora_auto_referencia_do_proprio_dominio(): void
    {
        Queue::fake();

        $selfUrl = config('app.url').'/outro-post';
        $this->withHeaders(['Referer' => $selfUrl])->get('/analytics-test-page');

        Queue::assertPushed(RecordPageViewJob::class, function (RecordPageViewJob $job) {
            return $job->referrer === null;
        });
    }

    #[Test]
    public function post_request_nao_dispara_job(): void
    {
        Queue::fake();

        $this->post('/login', ['email' => 'a@a.com', 'password' => '123']);

        Queue::assertNotPushed(RecordPageViewJob::class);
    }

    #[Test]
    public function primeira_visita_humana_cria_sessao_com_aquisicao_e_landing_page(): void
    {
        Queue::fake();

        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 Chrome/120.0 Windows',
            'Referer' => 'https://www.facebook.com/post/123?secret=discarded',
        ])->get('/analytics-test-page?utm_source=facebook&utm_medium=social&utm_campaign=calvino');

        $this->assertDatabaseHas('analytics_sessions', [
            'landing_path' => 'analytics-test-page',
            'initial_referrer_domain' => 'www.facebook.com',
            'source_key' => 'facebook',
            'utm_source' => 'facebook',
            'utm_medium' => 'social',
            'utm_campaign' => 'calvino',
            'is_bot' => false,
        ]);

        Queue::assertPushed(RecordPageViewJob::class, fn ($job) => $job->sessionId !== null
            && $job->pageReferrerDomain === 'www.facebook.com'
        );
    }

    #[Test]
    public function navegacao_reutiliza_sessao_e_preserva_origem_inicial(): void
    {
        Queue::fake();
        $ua = ['User-Agent' => 'Mozilla/5.0 Chrome/120.0 Windows'];

        $this->withHeaders([...$ua, 'Referer' => 'https://google.com/search'])->get('/analytics-test-page');
        $session = AnalyticsSession::firstOrFail();

        $this->withCookies([
            SessionTracker::VISITOR_COOKIE => $session->visitor_id,
            SessionTracker::SESSION_COOKIE => $session->id,
        ])->withHeaders([...$ua, 'Referer' => config('app.url').'/primeira'])->get('/analytics-test-page');

        $this->assertDatabaseCount('analytics_sessions', 1);
        $session->refresh();
        $this->assertSame('google', $session->source_key);
        $this->assertSame('google.com', $session->initial_referrer_domain);

        Queue::assertPushed(RecordPageViewJob::class, fn ($job) => $job->path === 'analytics-test-page' && $job->pageReferrer === '/primeira'
        );
    }

    #[Test]
    public function inatividade_de_trinta_minutos_cria_nova_sessao_para_o_mesmo_visitante(): void
    {
        Queue::fake();
        $headers = ['User-Agent' => 'Mozilla/5.0 Chrome/120.0 Windows'];

        $this->withHeaders($headers)->get('/analytics-test-page');
        $first = AnalyticsSession::firstOrFail();
        $first->update(['last_seen_at' => now()->subMinutes(31)]);

        $this->withCookies([
            SessionTracker::VISITOR_COOKIE => $first->visitor_id,
            SessionTracker::SESSION_COOKIE => $first->id,
        ])->withHeaders($headers)->get('/analytics-test-page');

        $this->assertDatabaseCount('analytics_sessions', 2);
        $this->assertSame(1, AnalyticsSession::distinct()->count('visitor_id'));
    }
}
