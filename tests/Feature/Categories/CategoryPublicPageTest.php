<?php

namespace Tests\Feature\Categories;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CategoryPublicPageTest extends TestCase
{
    use DatabaseTransactions;

    #[\PHPUnit\Framework\Attributes\Test]
    public function category_page_shows_published_posts(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => 'Tecnologia Pub', 'slug' => 'tecnologia-pub-test']);
        $post = Post::factory()->published()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
            'title'       => 'Artigo Publicado na Categoria',
        ]);

        $response = $this->get(route('categories.show', $category->slug));

        $response->assertOk();
        $response->assertSee('Artigo Publicado na Categoria');
        $response->assertSee('Tecnologia Pub');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function category_page_does_not_show_drafts(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['slug' => 'sem-rascunho-test']);
        Post::factory()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
            'title'       => 'Rascunho Privado',
            'published_at' => null,
        ]);

        $response = $this->get(route('categories.show', $category->slug));

        $response->assertOk();
        $response->assertDontSee('Rascunho Privado');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function category_page_shows_post_count(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['slug' => 'contagem-test']);
        Post::factory()->published()->count(3)->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);

        $response = $this->get(route('categories.show', $category->slug));

        $response->assertOk();
        $response->assertSee('3 artigos');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function unknown_category_slug_returns_404(): void
    {
        $response = $this->get(route('categories.show', 'slug-inexistente-xyz'));

        $response->assertNotFound();
    }
}
