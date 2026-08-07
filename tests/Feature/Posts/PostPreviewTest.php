<?php

namespace Tests\Feature\Posts;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Spatie\ResponseCache\Facades\ResponseCache;
use Tests\TestCase;

class PostPreviewTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        ResponseCache::clear();
    }

    protected function tearDown(): void
    {
        ResponseCache::clear();
        parent::tearDown();
    }

    #[Test]
    public function author_can_preview_draft_through_authenticated_route(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'title' => 'Rascunho em preview',
            'published_at' => null,
        ]);

        $this->actingAs($user)
            ->get(route('posts.preview', $post))
            ->assertOk()
            ->assertSee('Rascunho em preview');
    }

    #[Test]
    public function public_draft_url_stays_hidden_from_guests(): void
    {
        $post = Post::factory()->create(['published_at' => null]);

        $this->get(route('posts.show', $post->slug))
            ->assertNotFound();
    }

    #[Test]
    public function preview_route_does_not_increment_post_views(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->published()->create([
            'user_id' => $user->id,
            'views_count' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('posts.preview', $post))
            ->assertOk();

        $this->artisan('app:flush-views-buffer')->assertSuccessful();

        $this->assertEquals(0, $post->fresh()->views_count);
    }
}
