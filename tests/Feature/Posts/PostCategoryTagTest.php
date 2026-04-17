<?php

namespace Tests\Feature\Posts;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PostCategoryTagTest extends TestCase
{
    use DatabaseTransactions;

    #[\PHPUnit\Framework\Attributes\Test]
    public function creating_post_with_category_saves_category_id(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Volt::actingAs($user)
            ->test('posts.create')
            ->set('title', 'Artigo com Categoria')
            ->set('content', 'Conteúdo de teste para o artigo com categoria aqui.')
            ->set('category_id', $category->id)
            ->call('save');

        $post = Post::where('title', 'Artigo com Categoria')->first();
        $this->assertNotNull($post);
        $this->assertSame($category->id, $post->category_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function creating_post_without_category_leaves_it_null(): void
    {
        $user = User::factory()->create();

        Volt::actingAs($user)
            ->test('posts.create')
            ->set('title', 'Artigo Sem Categoria')
            ->set('content', 'Conteúdo de teste sem categoria aqui.')
            ->set('category_id', null)
            ->call('save');

        $post = Post::where('title', 'Artigo Sem Categoria')->first();
        $this->assertNotNull($post);
        $this->assertNull($post->category_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function creating_post_with_tags_creates_and_associates_them(): void
    {
        $user = User::factory()->create();

        Volt::actingAs($user)
            ->test('posts.create')
            ->set('title', 'Artigo com Tags')
            ->set('content', 'Conteúdo do artigo com tags para teste.')
            ->set('tags_input', 'Laravel, Docker, PHP')
            ->call('save');

        $post = Post::where('title', 'Artigo com Tags')->first();
        $this->assertNotNull($post);
        $this->assertCount(3, $post->tags);
        $this->assertTrue($post->tags->pluck('name')->contains('Laravel'));
        $this->assertTrue($post->tags->pluck('name')->contains('Docker'));
        $this->assertTrue($post->tags->pluck('name')->contains('PHP'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function creating_post_reuses_existing_tag(): void
    {
        $user = User::factory()->create();
        $existingTag = Tag::factory()->create(['name' => 'Reutilizado', 'slug' => 'reutilizado']);

        Volt::actingAs($user)
            ->test('posts.create')
            ->set('title', 'Artigo Reutilizando Tag')
            ->set('content', 'Conteúdo para reutilizar tag existente no teste.')
            ->set('tags_input', 'Reutilizado')
            ->call('save');

        $post = Post::where('title', 'Artigo Reutilizando Tag')->first();
        $this->assertNotNull($post);
        $this->assertSame($existingTag->id, $post->tags->first()->id);
        $this->assertDatabaseCount('tags', Tag::count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function editing_post_updates_category(): void
    {
        $user = User::factory()->create();
        $catA = Category::factory()->create();
        $catB = Category::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id, 'category_id' => $catA->id]);

        Volt::actingAs($user)
            ->test('posts.edit', ['post' => $post])
            ->set('category_id', $catB->id)
            ->call('save');

        $this->assertSame($catB->id, $post->fresh()->category_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function editing_post_syncs_tags(): void
    {
        $user = User::factory()->create();
        $tag1 = Tag::factory()->create(['name' => 'TagA', 'slug' => 'taga']);
        $tag2 = Tag::factory()->create(['name' => 'TagB', 'slug' => 'tagb']);
        $post = Post::factory()->create(['user_id' => $user->id]);
        $post->tags()->attach($tag1);

        Volt::actingAs($user)
            ->test('posts.edit', ['post' => $post])
            ->assertSet('tags_input', 'TagA')
            ->set('tags_input', 'TagB')
            ->call('save');

        $post->refresh();
        $this->assertCount(1, $post->tags);
        $this->assertSame($tag2->id, $post->tags->first()->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function editing_post_with_empty_tags_removes_all_tags(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create(['name' => 'ARemover', 'slug' => 'aremover-test']);
        $post = Post::factory()->create(['user_id' => $user->id]);
        $post->tags()->attach($tag);

        Volt::actingAs($user)
            ->test('posts.edit', ['post' => $post])
            ->set('tags_input', '')
            ->call('save');

        $this->assertCount(0, $post->fresh()->tags);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function edit_form_preloads_existing_category_and_tags(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $tag = Tag::factory()->create(['name' => 'PreTag', 'slug' => 'pretag-load']);
        $post = Post::factory()->create(['user_id' => $user->id, 'category_id' => $category->id]);
        $post->tags()->attach($tag);

        Volt::actingAs($user)
            ->test('posts.edit', ['post' => $post])
            ->assertSet('category_id', $category->id)
            ->assertSet('tags_input', 'PreTag');
    }
}
