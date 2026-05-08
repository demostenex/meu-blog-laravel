<?php

namespace Tests\Feature\Posts;

use App\Contracts\AiService;
use App\Jobs\GenerateEnglishVersionJob;
use App\Models\AiComment;
use App\Models\Post;
use App\Models\User;
use App\Services\AiServiceFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;
use Tests\TestCase;

class GenerateEnglishVersionJobTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $provider = $this->user->aiProviders()->create([
            'provider'   => 'gemini',
            'api_key'    => 'fake-key',
            'is_default' => true,
        ]);
        $provider->models()->create(['model' => 'gemini-2.0-flash', 'is_default' => true]);
    }

    private function mockAiService(): AiService
    {
        $mock = \Mockery::mock(AiService::class);

        $factoryMock = $this->mock(AiServiceFactory::class);
        $factoryMock->shouldReceive('for')->andReturn($mock);

        return $mock;
    }

    // ── Dispatch via edit page ────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function edit_page_dispatches_job_instead_of_running_inline(): void
    {
        Queue::fake();

        $post = Post::factory()->create(['user_id' => $this->user->id]);

        Volt::actingAs($this->user)
            ->test('posts.edit', ['post' => $post])
            ->call('generateEnglishVersion');

        Queue::assertPushed(GenerateEnglishVersionJob::class, function ($job) use ($post) {
            return $job->postId === $post->id;
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function edit_page_sets_status_to_pending_after_dispatch(): void
    {
        Queue::fake();

        $post = Post::factory()->create(['user_id' => $this->user->id]);

        Volt::actingAs($this->user)
            ->test('posts.edit', ['post' => $post])
            ->call('generateEnglishVersion');

        $this->assertSame('pending', $post->fresh()->content_en_status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function edit_page_does_not_dispatch_when_no_provider_configured(): void
    {
        Queue::fake();

        $user = User::factory()->create(); // sem provider
        $post = Post::factory()->create(['user_id' => $user->id]);

        Volt::actingAs($user)
            ->test('posts.edit', ['post' => $post])
            ->call('generateEnglishVersion')
            ->assertSet('englishStatus', 'error:Configure um AI provider nas configurações de IA primeiro.');

        Queue::assertNothingPushed();
    }

    // ── Job execution ─────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function job_translates_title_and_content_and_sets_status_done(): void
    {
        $post = Post::factory()->create([
            'user_id'           => $this->user->id,
            'title'             => 'Meu Artigo',
            'content'           => '<p>Conteúdo <a href="https://example.com">aqui</a>.</p>',
            'content_en_status' => 'pending',
        ]);

        $service = $this->mockAiService();
        $service->shouldReceive('translateText')->once()->andReturn('My Article');
        $service->shouldReceive('translateHtml')->once()->andReturn('<p>Content <a href="https://example.com">here</a>.</p>');

        (new GenerateEnglishVersionJob($post->id))->handle(app(AiServiceFactory::class));

        $post->refresh();

        $this->assertSame('My Article', $post->title_en);
        $this->assertStringContainsString('https://example.com', $post->content_en);
        $this->assertSame('done', $post->content_en_status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function job_also_translates_ai_comment_when_present(): void
    {
        $post = Post::factory()->create([
            'user_id'           => $this->user->id,
            'content_en_status' => 'pending',
        ]);

        AiComment::create([
            'post_id'    => $post->id,
            'content'    => 'Comentário em português.',
            'content_en' => null,
            'model'      => 'gemini-2.0-flash',
        ]);

        $service = $this->mockAiService();
        $service->shouldReceive('translateText')->twice()->andReturnValues(['Title EN', 'Comment in English.']);
        $service->shouldReceive('translateHtml')->once()->andReturn('<p>Content EN</p>');

        (new GenerateEnglishVersionJob($post->id))->handle(app(AiServiceFactory::class));

        $this->assertSame('Comment in English.', $post->latestAiComment->fresh()->content_en);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function job_sets_status_error_and_rethrows_on_api_failure(): void
    {
        $post = Post::factory()->create([
            'user_id'           => $this->user->id,
            'content_en_status' => 'pending',
        ]);

        $service = $this->mockAiService();
        $service->shouldReceive('translateText')->once()->andThrow(new \RuntimeException('API quota exceeded.'));

        $this->expectException(\RuntimeException::class);

        (new GenerateEnglishVersionJob($post->id))->handle(app(AiServiceFactory::class));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function job_saves_error_message_on_failure(): void
    {
        $post = Post::factory()->create([
            'user_id'           => $this->user->id,
            'content_en_status' => 'pending',
        ]);

        $service = $this->mockAiService();
        $service->shouldReceive('translateText')->once()->andThrow(new \RuntimeException('API quota exceeded.'));

        try {
            (new GenerateEnglishVersionJob($post->id))->handle(app(AiServiceFactory::class));
        } catch (\RuntimeException) {
        }

        $post->refresh();
        $this->assertSame('error', $post->content_en_status);
        $this->assertSame('API quota exceeded.', $post->content_en_error);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function job_clears_error_message_on_success(): void
    {
        $post = Post::factory()->create([
            'user_id'           => $this->user->id,
            'content_en_status' => 'error',
            'content_en_error'  => 'Erro anterior.',
        ]);

        $service = $this->mockAiService();
        $service->shouldReceive('translateText')->once()->andReturn('Title EN');
        $service->shouldReceive('translateHtml')->once()->andReturn('<p>Content EN</p>');

        (new GenerateEnglishVersionJob($post->id))->handle(app(AiServiceFactory::class));

        $post->refresh();
        $this->assertSame('done', $post->content_en_status);
        $this->assertNull($post->content_en_error);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function job_skips_ai_comment_translation_when_already_translated(): void
    {
        $post = Post::factory()->create([
            'user_id'           => $this->user->id,
            'content_en_status' => 'pending',
        ]);

        AiComment::create([
            'post_id'    => $post->id,
            'content'    => 'Original.',
            'content_en' => 'Already translated.',
            'model'      => 'gemini-2.0-flash',
        ]);

        $service = $this->mockAiService();
        $service->shouldReceive('translateText')->once()->andReturn('Title EN');
        $service->shouldReceive('translateHtml')->once()->andReturn('<p>EN</p>');

        (new GenerateEnglishVersionJob($post->id))->handle(app(AiServiceFactory::class));

        $this->assertSame('Already translated.', $post->latestAiComment->fresh()->content_en);
    }

    // ── Human-in-the-loop lock ───────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function job_skips_translation_when_content_en_is_locked(): void
    {
        $post = Post::factory()->create([
            'user_id'           => $this->user->id,
            'title_en'          => 'Original EN Title',
            'content_en'        => '<p>Original EN content.</p>',
            'content_en_status' => 'pending',
            'content_en_locked' => true,
        ]);

        $factoryMock = $this->mock(AiServiceFactory::class);
        $factoryMock->shouldNotReceive('for');

        (new GenerateEnglishVersionJob($post->id))->handle(app(AiServiceFactory::class));

        $post->refresh();

        $this->assertSame('Original EN Title', $post->title_en);
        $this->assertSame('<p>Original EN content.</p>', $post->content_en);
        $this->assertSame('done', $post->content_en_status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function toggle_lock_button_flips_content_en_locked(): void
    {
        $post = Post::factory()->create([
            'user_id'           => $this->user->id,
            'content_en'        => '<p>EN content</p>',
            'content_en_status' => 'done',
            'content_en_locked' => false,
        ]);

        Volt::actingAs($this->user)
            ->test('posts.edit', ['post' => $post])
            ->call('toggleEnglishLock');

        $this->assertTrue($post->fresh()->content_en_locked);

        Volt::actingAs($this->user)
            ->test('posts.edit', ['post' => $post->fresh()])
            ->call('toggleEnglishLock');

        $this->assertFalse($post->fresh()->content_en_locked);
    }

    // ── Revisão manual da tradução ───────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function author_can_save_manual_translation_review(): void
    {
        $post = Post::factory()->create([
            'user_id'           => $this->user->id,
            'title_en'          => 'Original EN Title',
            'content_en'        => '<p>Original EN content.</p>',
            'content_en_status' => 'done',
            'content_en_locked' => false,
        ]);

        Volt::actingAs($this->user)
            ->test('posts.edit', ['post' => $post])
            ->set('titleEnEdit', 'Corrected EN Title')
            ->set('contentEnEdit', '<p>Corrected EN content.</p>')
            ->call('saveEnglishReview');

        $post->refresh();
        $this->assertSame('Corrected EN Title', $post->title_en);
        $this->assertSame('<p>Corrected EN content.</p>', $post->content_en);
        $this->assertTrue($post->content_en_locked);
        $this->assertSame('done', $post->content_en_status);
        $this->assertNull($post->content_en_error);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function save_review_requires_title_and_content(): void
    {
        $post = Post::factory()->create([
            'user_id'    => $this->user->id,
            'title_en'   => 'EN Title',
            'content_en' => '<p>EN content.</p>',
        ]);

        Volt::actingAs($this->user)
            ->test('posts.edit', ['post' => $post])
            ->set('titleEnEdit', '')
            ->set('contentEnEdit', '')
            ->call('saveEnglishReview')
            ->assertHasErrors(['titleEnEdit', 'contentEnEdit']);
    }

    // ── Polling status via edit page ──────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function refresh_english_status_sets_success_when_done(): void
    {
        $post = Post::factory()->create([
            'user_id'           => $this->user->id,
            'content_en_status' => 'done',
            'content_en'        => '<p>EN content</p>',
        ]);

        Volt::actingAs($this->user)
            ->test('posts.edit', ['post' => $post])
            ->call('refreshEnglishStatus')
            ->assertSet('englishStatus', 'success');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function refresh_english_status_sets_error_when_failed(): void
    {
        $post = Post::factory()->create([
            'user_id'           => $this->user->id,
            'content_en_status' => 'error',
            'content_en_error'  => null,
        ]);

        Volt::actingAs($this->user)
            ->test('posts.edit', ['post' => $post])
            ->call('refreshEnglishStatus')
            ->assertSet('englishStatus', 'error:Falha ao gerar a versão em inglês. Tente novamente.');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function refresh_english_status_shows_real_error_message(): void
    {
        $post = Post::factory()->create([
            'user_id'           => $this->user->id,
            'content_en_status' => 'error',
            'content_en_error'  => 'API quota exceeded.',
        ]);

        Volt::actingAs($this->user)
            ->test('posts.edit', ['post' => $post])
            ->call('refreshEnglishStatus')
            ->assertSet('englishStatus', 'error:API quota exceeded.');
    }
}
