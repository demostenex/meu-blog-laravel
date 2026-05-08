<?php

namespace Tests\Unit\Services;

use App\Models\Post;
use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GeminiServiceCommentTest extends TestCase
{
    use DatabaseTransactions;

    private function makeService(?string $persona = null): GeminiService
    {
        return new GeminiService(apiKey: 'fake', model: 'gemini-2.0-flash', persona: $persona);
    }

    private function callBuildPrompt(GeminiService $service, Post $post): string
    {
        $ref = new \ReflectionMethod($service, 'buildCommentPrompt');
        $ref->setAccessible(true);
        return $ref->invoke($service, $post);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function prompt_contains_article_title_and_content(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->published()->create([
            'user_id' => $user->id,
            'title'   => 'Artigo Principal',
            'content' => '<p>Conteúdo do artigo principal aqui.</p>',
        ]);

        $prompt = $this->callBuildPrompt($this->makeService(), $post);

        $this->assertStringContainsString('Artigo Principal', $prompt);
        $this->assertStringContainsString('Conteúdo do artigo principal aqui.', $prompt);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function prompt_uses_default_persona_when_none_set(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->published()->create(['user_id' => $user->id]);

        $prompt = $this->callBuildPrompt($this->makeService(persona: null), $post);

        $this->assertStringContainsString('sarcástico', $prompt);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function prompt_uses_custom_persona_when_set(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->published()->create(['user_id' => $user->id]);

        $prompt = $this->callBuildPrompt($this->makeService(persona: 'Você é um filósofo zen.'), $post);

        $this->assertStringContainsString('filósofo zen', $prompt);
        $this->assertStringNotContainsString('sarcástico', $prompt);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function prompt_includes_recent_posts_as_memory(): void
    {
        $user    = User::factory()->create();
        $post = Post::factory()->published()->create(['user_id' => $user->id, 'title' => 'Artigo Atual']);
        Post::factory()->published()->create(['user_id' => $user->id, 'title' => 'Artigo Anterior', 'content' => '<p>Conteúdo antigo.</p>']);

        $prompt = $this->callBuildPrompt($this->makeService(), $post);

        $this->assertStringContainsString('Artigo Anterior', $prompt);
        $this->assertStringContainsString('Conteúdo antigo.', $prompt);
        $this->assertStringContainsString('Outros artigos do blog', $prompt);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function prompt_does_not_include_current_post_in_memory(): void
    {
        $user        = User::factory()->create();
        $uniqueTitle = 'TituloExclusivoXYZ987';
        $post        = Post::factory()->published()->create(['user_id' => $user->id, 'title' => $uniqueTitle]);
        // outros posts para garantir que o bloco de memória seja gerado
        Post::factory()->published()->count(2)->create(['user_id' => $user->id]);

        $prompt = $this->callBuildPrompt($this->makeService(), $post);

        // o título do artigo atual deve aparecer exatamente uma vez (na linha "Título:"), nunca na memória
        $this->assertSame(1, substr_count($prompt, $uniqueTitle));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function prompt_does_not_include_unpublished_posts_in_memory(): void
    {
        $user  = User::factory()->create();
        $post  = Post::factory()->published()->create(['user_id' => $user->id]);
        Post::factory()->create(['user_id' => $user->id, 'title' => 'Rascunho Secreto', 'published_at' => null]);

        $prompt = $this->callBuildPrompt($this->makeService(), $post);

        $this->assertStringNotContainsString('Rascunho Secreto', $prompt);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function prompt_includes_at_most_five_recent_posts(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->published()->create(['user_id' => $user->id]);
        Post::factory()->published()->count(7)->create(['user_id' => $user->id]);

        $prompt = $this->callBuildPrompt($this->makeService(), $post);

        // Count occurrences of the memory list marker
        $this->assertLessThanOrEqual(5, substr_count($prompt, "\n- \""));
    }
}
