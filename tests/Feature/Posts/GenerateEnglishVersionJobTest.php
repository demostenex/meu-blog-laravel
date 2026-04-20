<?php

namespace Tests\Feature\Posts;

use App\Jobs\GenerateEnglishVersionJob;
use App\Models\AiComment;
use App\Models\Post;
use App\Models\User;
use App\Services\GeminiService;
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

        $this->user = User::factory()->create([
            'gemini_api_key' => encrypt('fake-key'),
            'gemini_model'   => 'gemini-2.0-flash',
        ]);
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
    public function edit_page_does_not_dispatch_when_no_api_key(): void
    {
        Queue::fake();

        $user = User::factory()->create(['gemini_api_key' => null]);
        $post = Post::factory()->create(['user_id' => $user->id]);

        Volt::actingAs($user)
            ->test('posts.edit', ['post' => $post])
            ->call('generateEnglishVersion')
            ->assertSet('englishStatus', 'error:Configure a chave de API do Gemini no seu perfil primeiro.');

        Queue::assertNothingPushed();
    }

    // ── Job execution ─────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function job_translates_title_and_content_and_sets_status_done(): void
    {
        $post = Post::factory()->create([
            'user_id'  => $this->user->id,
            'title'    => 'Meu Artigo',
            'content'  => '<p>Conteúdo <a href="https://example.com">aqui</a>.</p>',
            'content_en_status' => 'pending',
        ]);

        $this->mock(GeminiService::class)
            ->shouldReceive('translateText')
            ->once()
            ->andReturn('My Article')
            ->shouldReceive('translateHtml')
            ->once()
            ->andReturn('<p>Content <a href="https://example.com">here</a>.</p>');

        (new GenerateEnglishVersionJob($post->id))->handle(app(GeminiService::class));

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

        $this->mock(GeminiService::class)
            ->shouldReceive('translateText')
            ->twice()
            ->andReturnValues(['Title EN', 'Comment in English.'])
            ->shouldReceive('translateHtml')
            ->once()
            ->andReturn('<p>Content EN</p>');

        (new GenerateEnglishVersionJob($post->id))->handle(app(GeminiService::class));

        $this->assertSame('Comment in English.', $post->latestAiComment->fresh()->content_en);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function job_sets_status_error_and_rethrows_on_api_failure(): void
    {
        $post = Post::factory()->create([
            'user_id'           => $this->user->id,
            'content_en_status' => 'pending',
        ]);

        $this->mock(GeminiService::class)
            ->shouldReceive('translateText')
            ->once()
            ->andThrow(new \RuntimeException('API quota exceeded.'));

        $this->expectException(\RuntimeException::class);

        (new GenerateEnglishVersionJob($post->id))->handle(app(GeminiService::class));

        $this->assertSame('error', $post->fresh()->content_en_status);
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

        $this->mock(GeminiService::class)
            ->shouldReceive('translateText')->once()->andReturn('Title EN')
            ->shouldReceive('translateHtml')->once()->andReturn('<p>EN</p>');

        (new GenerateEnglishVersionJob($post->id))->handle(app(GeminiService::class));

        // content_en of ai_comment unchanged
        $this->assertSame('Already translated.', $post->latestAiComment->fresh()->content_en);
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
        ]);

        Volt::actingAs($this->user)
            ->test('posts.edit', ['post' => $post])
            ->call('refreshEnglishStatus')
            ->assertSet('englishStatus', 'error:Falha ao gerar a versão em inglês. Tente novamente.');
    }
}
