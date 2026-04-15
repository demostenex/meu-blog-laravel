<?php

namespace Tests\Feature\Posts;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Volt\Volt;
use Tests\TestCase;

class UpdatePostSlugTest extends TestCase
{
    use DatabaseTransactions;

    #[\PHPUnit\Framework\Attributes\Test]
    public function changing_post_title_updates_slug(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'title'   => 'Titulo Antigo',
            'slug'    => 'titulo-antigo-abc123',
        ]);

        Volt::actingAs($user)
            ->test('posts.edit', ['post' => $post])
            ->set('title', 'Novo Titulo do Artigo')
            ->call('saveDraft');

        $post->refresh();

        $this->assertSame('Novo Titulo do Artigo', $post->title);
        $this->assertSame("novo-titulo-do-artigo-{$post->id}", $post->slug);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function keeping_the_same_title_preserves_slug(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'title'   => 'Titulo Estavel',
            'slug'    => 'titulo-estavel-custom',
        ]);

        Volt::actingAs($user)
            ->test('posts.edit', ['post' => $post])
            ->set('title', 'Titulo Estavel')
            ->call('saveDraft');

        $post->refresh();

        $this->assertSame('titulo-estavel-custom', $post->slug);
    }
}
