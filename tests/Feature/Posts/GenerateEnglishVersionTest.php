<?php

namespace Tests\Feature\Posts;

use App\Models\AiComment;
use App\Models\Post;
use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Volt\Volt;
use Tests\TestCase;

class GenerateEnglishVersionTest extends TestCase
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

    #[\PHPUnit\Framework\Attributes\Test]
    public function generate_english_version_saves_translated_fields_to_database(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'title'   => 'Meu Artigo',
            'content' => '<p>Conteúdo original.</p>',
        ]);

        $this->mock(GeminiService::class)
            ->shouldReceive('translateText')
            ->times(2)
            ->andReturnValues(['My Article', 'Original content.']);

        Volt::actingAs($this->user)
            ->test('posts.edit', ['post' => $post])
            ->call('generateEnglishVersion')
            ->assertHasNoErrors()
            ->assertSet('englishStatus', 'success');

        $post->refresh();

        $this->assertSame('My Article', $post->title_en);
        $this->assertSame('Original content.', $post->content_en);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function generate_english_version_also_translates_ai_comment_if_exists(): void
    {
        $post = Post::factory()->create(['user_id' => $this->user->id]);

        AiComment::create([
            'post_id' => $post->id,
            'content' => 'Comentário original da IA.',
            'model'   => 'gemini-2.0-flash',
        ]);

        $this->mock(GeminiService::class)
            ->shouldReceive('translateText')
            ->times(3)
            ->andReturnValues(['Title EN', 'Content EN', 'AI comment in English.']);

        Volt::actingAs($this->user)
            ->test('posts.edit', ['post' => $post->load('aiComments')])
            ->call('generateEnglishVersion')
            ->assertSet('englishStatus', 'success');

        $this->assertSame('AI comment in English.', $post->latestAiComment->fresh()->content_en);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function generate_english_version_shows_error_when_no_api_key(): void
    {
        $user = User::factory()->create(['gemini_api_key' => null]);
        $post = Post::factory()->create(['user_id' => $user->id]);

        Volt::actingAs($user)
            ->test('posts.edit', ['post' => $post])
            ->call('generateEnglishVersion')
            ->assertSet('englishStatus', 'error:Configure a chave de API do Gemini no seu perfil primeiro.');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function generate_english_version_shows_error_on_api_failure(): void
    {
        $post = Post::factory()->create(['user_id' => $this->user->id]);

        $this->mock(GeminiService::class)
            ->shouldReceive('translateText')
            ->once()
            ->andThrow(new \RuntimeException('API quota exceeded.'));

        Volt::actingAs($this->user)
            ->test('posts.edit', ['post' => $post])
            ->call('generateEnglishVersion')
            ->assertSet('englishStatus', 'error:API quota exceeded.');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function retranslate_overwrites_previous_english_version(): void
    {
        $post = Post::factory()->create([
            'user_id'    => $this->user->id,
            'title_en'   => 'Old Title EN',
            'content_en' => 'Old content EN.',
        ]);

        $this->mock(GeminiService::class)
            ->shouldReceive('translateText')
            ->times(2)
            ->andReturnValues(['New Title EN', 'New content EN.']);

        Volt::actingAs($this->user)
            ->test('posts.edit', ['post' => $post])
            ->call('generateEnglishVersion')
            ->assertSet('englishStatus', 'success');

        $post->refresh();

        $this->assertSame('New Title EN', $post->title_en);
        $this->assertSame('New content EN.', $post->content_en);
    }
}
