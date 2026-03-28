<?php

namespace Tests\Feature\Posts;

use App\Models\Post;
use App\Models\User;
use App\Services\ImagenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class GenerateAiCoverTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'gemini_api_key' => encrypt('fake-key-test'),
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function generate_ai_cover_requires_prompt(): void
    {
        $post = Post::factory()->create(['user_id' => $this->user->id]);

        Volt::actingAs($this->user)
            ->test('posts.edit', ['post' => $post])
            ->set('cover_image_prompt', '')
            ->call('generateAiCover')
            ->assertHasErrors(['cover_image_prompt' => 'required']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function generate_ai_cover_calls_imagen_service_and_updates_post(): void
    {
        $fakePath = 'covers/fake-cover.webp';

        $this->mock(ImagenService::class)
            ->shouldReceive('generateCoverImage')
            ->once()
            ->andReturn($fakePath);

        $post = Post::factory()->create([
            'user_id'      => $this->user->id,
            'cover_image'  => null,
        ]);

        Volt::actingAs($this->user)
            ->test('posts.edit', ['post' => $post])
            ->set('cover_image_prompt', 'Paisagem futurista ao pôr do sol')
            ->set('cover_image_use_content', true)
            ->set('cover_image_use_bio', false)
            ->call('generateAiCover')
            ->assertHasNoErrors()
            ->assertSet('coverStatus', 'success');

        $post->refresh();

        $this->assertEquals($fakePath, $post->cover_image);
        $this->assertEquals('Paisagem futurista ao pôr do sol', $post->cover_image_prompt);
        $this->assertTrue($post->cover_image_use_content);
        $this->assertFalse($post->cover_image_use_bio);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function generate_ai_cover_shows_error_on_api_failure(): void
    {
        $this->mock(ImagenService::class)
            ->shouldReceive('generateCoverImage')
            ->once()
            ->andThrow(new \RuntimeException('Nenhuma imagem retornada pelo Nano Banana.'));

        $post = Post::factory()->create(['user_id' => $this->user->id]);

        Volt::actingAs($this->user)
            ->test('posts.edit', ['post' => $post])
            ->set('cover_image_prompt', 'Teste que vai falhar')
            ->call('generateAiCover')
            ->assertHasNoErrors()
            ->assertSet('coverStatus', 'error:Nenhuma imagem retornada pelo Nano Banana.');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function generate_ai_cover_on_create_stores_path_in_property(): void
    {
        $fakePath = 'covers/fake-new-cover.webp';

        $this->mock(ImagenService::class)
            ->shouldReceive('generateCoverImage')
            ->once()
            ->andReturn($fakePath);

        Volt::actingAs($this->user)
            ->test('posts.create')
            ->set('cover_image_prompt', 'Astronauta no espaço')
            ->call('generateAiCover')
            ->assertHasNoErrors()
            ->assertSet('ai_generated_cover_path', $fakePath)
            ->assertSet('coverStatus', 'success');
    }
}
