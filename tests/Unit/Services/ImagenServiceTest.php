<?php

namespace Tests\Unit\Services;

use App\Models\Post;
use App\Models\User;
use App\Services\ImagenService;
use PHPUnit\Framework\TestCase;

class ImagenServiceTest extends TestCase
{
    private function makeService(): ImagenService
    {
        return new ImagenService();
    }

    private function makeUser(array $attrs = []): User
    {
        $user = new User();
        $user->about_me = $attrs['about_me'] ?? 'Desenvolvedor apaixonado por tecnologia.';

        return $user;
    }

    private function makePost(array $attrs = []): Post
    {
        $post = new Post();
        $post->title                   = $attrs['title']                   ?? 'Meu Artigo Incrível';
        $post->content                 = $attrs['content']                 ?? '<p>Conteúdo do artigo aqui.</p>';
        $post->cover_image_prompt      = $attrs['cover_image_prompt']      ?? 'Um desenvolvedor ao pôr do sol';
        $post->cover_image_use_content = $attrs['cover_image_use_content'] ?? false;
        $post->cover_image_use_bio     = $attrs['cover_image_use_bio']     ?? false;

        return $post;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function prompt_contains_base_prompt(): void
    {
        $service = $this->makeService();
        $post    = $this->makePost(['cover_image_prompt' => 'Paisagem futurista azul']);
        $user    = $this->makeUser();

        $prompt = $this->callBuildPrompt($service, $post, $user);

        $this->assertStringContainsString('Paisagem futurista azul', $prompt);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function prompt_without_optional_context(): void
    {
        $service = $this->makeService();
        $post    = $this->makePost([
            'cover_image_use_content' => false,
            'cover_image_use_bio'     => false,
        ]);
        $user = $this->makeUser();

        $prompt = $this->callBuildPrompt($service, $post, $user);

        $this->assertStringNotContainsString('Contexto do artigo', $prompt);
        $this->assertStringNotContainsString('Sobre o autor', $prompt);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function prompt_includes_article_content_when_enabled(): void
    {
        $service = $this->makeService();
        $post    = $this->makePost([
            'content'                 => '<p>Texto especial do artigo para teste.</p>',
            'cover_image_use_content' => true,
            'cover_image_use_bio'     => false,
        ]);
        $user = $this->makeUser();

        $prompt = $this->callBuildPrompt($service, $post, $user);

        $this->assertStringContainsString('Contexto do artigo', $prompt);
        $this->assertStringContainsString('Texto especial do artigo para teste.', $prompt);
        $this->assertStringNotContainsString('Sobre o autor', $prompt);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function prompt_includes_author_bio_when_enabled(): void
    {
        $service = $this->makeService();
        $post    = $this->makePost([
            'cover_image_use_content' => false,
            'cover_image_use_bio'     => true,
        ]);
        $user = $this->makeUser(['about_me' => 'Bio única do autor para validação.']);

        $prompt = $this->callBuildPrompt($service, $post, $user);

        $this->assertStringContainsString('Sobre o autor do blog', $prompt);
        $this->assertStringContainsString('Bio única do autor para validação.', $prompt);
        $this->assertStringNotContainsString('Contexto do artigo', $prompt);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function prompt_includes_both_contexts_when_both_enabled(): void
    {
        $service = $this->makeService();
        $post    = $this->makePost([
            'content'                 => '<p>Conteúdo completo aqui.</p>',
            'cover_image_use_content' => true,
            'cover_image_use_bio'     => true,
        ]);
        $user = $this->makeUser(['about_me' => 'Bio do autor aqui.']);

        $prompt = $this->callBuildPrompt($service, $post, $user);

        $this->assertStringContainsString('Contexto do artigo', $prompt);
        $this->assertStringContainsString('Sobre o autor do blog', $prompt);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function prompt_always_ends_with_style_guideline(): void
    {
        $service = $this->makeService();
        $post    = $this->makePost();
        $user    = $this->makeUser();

        $prompt = $this->callBuildPrompt($service, $post, $user);

        $this->assertStringContainsString('fotografia profissional', $prompt);
        $this->assertStringContainsString('16:9', $prompt);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function prompt_skips_bio_when_user_has_no_about_me(): void
    {
        $service         = $this->makeService();
        $post            = $this->makePost(['cover_image_use_bio' => true]);
        $user            = $this->makeUser();
        $user->about_me  = null;

        $prompt = $this->callBuildPrompt($service, $post, $user);

        $this->assertStringNotContainsString('Sobre o autor do blog', $prompt);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function prompt_strips_html_tags_from_content(): void
    {
        $service = $this->makeService();
        $post    = $this->makePost([
            'content'                 => '<h1>Título</h1><p>Parágrafo <strong>em negrito</strong>.</p>',
            'cover_image_use_content' => true,
        ]);
        $user = $this->makeUser();

        $prompt = $this->callBuildPrompt($service, $post, $user);

        $this->assertStringNotContainsString('<h1>', $prompt);
        $this->assertStringNotContainsString('<p>', $prompt);
        $this->assertStringContainsString('Parágrafo', $prompt);
    }

    private function callBuildPrompt(ImagenService $service, Post $post, User $user): string
    {
        $reflection = new \ReflectionMethod($service, 'buildPrompt');
        $reflection->setAccessible(true);

        return $reflection->invoke($service, $post, $user);
    }
}
