<?php

namespace Tests\Feature\Analytics;

use App\Models\PageView;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use App\Jobs\RecordPageViewJob;
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

        $this->get('/');

        Queue::assertPushed(RecordPageViewJob::class);
    }

    #[Test]
    public function usuario_autenticado_nao_dispara_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user)->get('/');

        Queue::assertNotPushed(RecordPageViewJob::class);
    }

    #[Test]
    public function bot_dispara_job_marcado_como_bot(): void
    {
        Queue::fake();

        $this->withHeaders(['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])
            ->get('/');

        Queue::assertPushed(RecordPageViewJob::class, function (RecordPageViewJob $job) {
            return $job->isBot === true;
        });
    }

    #[Test]
    public function visita_humana_dispara_job_sem_flag_bot(): void
    {
        Queue::fake();

        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'])
            ->get('/');

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
            path:      "blog/{$post->slug}",
            referrer:  'google.com',
            device:    'desktop',
            ipHash:    hash('sha256', '127.0.0.1' . config('app.key')),
            userAgent: 'Mozilla/5.0',
            viewToken: '00000000-0000-0000-0000-000000000001',
            isBot:     false,
        ))->handle();

        $this->assertDatabaseHas('page_views', [
            'path'     => "blog/{$post->slug}",
            'referrer' => 'google.com',
            'device'   => 'desktop',
            'is_bot'   => false,
        ]);
    }

    #[Test]
    public function job_salva_bot_com_flag_correta(): void
    {
        (new RecordPageViewJob(
            path:      'blog/algum-post',
            referrer:  null,
            device:    'desktop',
            ipHash:    hash('sha256', '10.0.0.1' . config('app.key')),
            userAgent: 'Googlebot/2.1',
            viewToken: '00000000-0000-0000-0000-000000000002',
            isBot:     true,
        ))->handle();

        $this->assertDatabaseHas('page_views', [
            'path'   => 'blog/algum-post',
            'is_bot' => true,
        ]);
    }

    #[Test]
    public function detects_mobile_user_agent(): void
    {
        Queue::fake();

        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) Mobile/15E148'])
            ->get('/');

        Queue::assertPushed(RecordPageViewJob::class, function (RecordPageViewJob $job) {
            return $job->device === 'mobile';
        });
    }

    #[Test]
    public function detects_tablet_user_agent(): void
    {
        Queue::fake();

        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPad; CPU OS 14_0 like Mac OS X)'])
            ->get('/');

        Queue::assertPushed(RecordPageViewJob::class, function (RecordPageViewJob $job) {
            return $job->device === 'tablet';
        });
    }

    #[Test]
    public function extrai_host_do_referrer(): void
    {
        Queue::fake();

        $this->withHeaders(['Referer' => 'https://google.com/search?q=blog'])
            ->get('/');

        Queue::assertPushed(RecordPageViewJob::class, function (RecordPageViewJob $job) {
            return $job->referrer === 'google.com';
        });
    }

    #[Test]
    public function ignora_auto_referencia_do_proprio_dominio(): void
    {
        Queue::fake();

        $selfUrl = config('app.url') . '/outro-post';
        $this->withHeaders(['Referer' => $selfUrl])->get('/');

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
}
