<?php

namespace Tests\Feature\Tags;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TagPublicPageTest extends TestCase
{
    use DatabaseTransactions;

    #[\PHPUnit\Framework\Attributes\Test]
    public function tag_page_shows_published_posts(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create(['name' => 'Laravel', 'slug' => 'laravel-pub-test']);
        $post = Post::factory()->published()->create([
            'user_id' => $user->id,
            'title'   => 'Artigo com Tag Laravel',
        ]);
        $post->tags()->attach($tag);

        $response = $this->get(route('tags.show', $tag->slug));

        $response->assertOk();
        $response->assertSee('Artigo com Tag Laravel');
        $response->assertSee('Laravel');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function tag_page_does_not_show_drafts(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create(['slug' => 'tag-draft-test']);
        $post = Post::factory()->create([
            'user_id'     => $user->id,
            'title'       => 'Rascunho com Tag',
            'published_at' => null,
        ]);
        $post->tags()->attach($tag);

        $response = $this->get(route('tags.show', $tag->slug));

        $response->assertOk();
        $response->assertDontSee('Rascunho com Tag');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function tag_page_shows_post_count(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create(['slug' => 'tag-count-test']);
        $posts = Post::factory()->published()->count(2)->create(['user_id' => $user->id]);
        $posts->each(fn($p) => $p->tags()->attach($tag));

        $response = $this->get(route('tags.show', $tag->slug));

        $response->assertOk();
        $response->assertSee('2 artigos');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function unknown_tag_slug_returns_404(): void
    {
        $response = $this->get(route('tags.show', 'tag-inexistente-xyz'));

        $response->assertNotFound();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function tag_page_shows_category_of_posts(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create(['slug' => 'tag-with-cat-test']);

        /** @var \App\Models\Category $category */
        $category = \App\Models\Category::factory()->create(['name' => 'CategoriaNaTag', 'slug' => 'categoria-na-tag-test']);
        $post = Post::factory()->published()->create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);
        $post->tags()->attach($tag);

        $response = $this->get(route('tags.show', $tag->slug));

        $response->assertOk();
        $response->assertSee('CategoriaNaTag');
    }
}
