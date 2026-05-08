<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Spatie\ResponseCache\Facades\ResponseCache;
use Tests\TestCase;

class ResponseCacheTest extends TestCase
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
    public function home_page_is_cached_on_second_request(): void
    {
        $r1 = $this->get('/');
        $r1->assertOk();
        $this->assertEquals('MISS', $r1->headers->get('X-Cache-Status'));

        $r2 = $this->get('/');
        $r2->assertOk();
        $this->assertEquals('HIT', $r2->headers->get('X-Cache-Status'));
    }

    #[Test]
    public function post_page_is_cached_on_second_request(): void
    {
        $post = Post::factory()->published()->create();

        $r1 = $this->get("/blog/{$post->slug}");
        $r1->assertOk();
        $this->assertEquals('MISS', $r1->headers->get('X-Cache-Status'));

        $r2 = $this->get("/blog/{$post->slug}");
        $r2->assertOk();
        $this->assertEquals('HIT', $r2->headers->get('X-Cache-Status'));
    }

    #[Test]
    public function authenticated_user_never_gets_cached_response(): void
    {
        $user = User::factory()->create();

        $r1 = $this->actingAs($user)->get('/');
        $r1->assertOk();
        $this->assertNotEquals('HIT', $r1->headers->get('X-Cache-Status'));

        $r2 = $this->actingAs($user)->get('/');
        $r2->assertOk();
        $this->assertNotEquals('HIT', $r2->headers->get('X-Cache-Status'));
    }

    #[Test]
    public function post_view_is_counted_even_on_cache_hit(): void
    {
        $post = Post::factory()->published()->create(['views_count' => 0]);

        // 1ª request: MISS — incrementa via middleware TrackPostView
        $this->get("/blog/{$post->slug}")->assertOk();

        // 2ª request: HIT — middleware ainda roda antes do cache retornar
        $this->get("/blog/{$post->slug}")->assertOk();

        // Flush do Redis para o banco
        $this->artisan('app:flush-views-buffer')->assertSuccessful();

        $this->assertEquals(2, $post->fresh()->views_count);
    }

    #[Test]
    public function author_own_post_view_is_not_counted(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->published()->create(['user_id' => $user->id, 'views_count' => 0]);

        $this->actingAs($user)->get("/blog/{$post->slug}")->assertOk();
        $this->artisan('app:flush-views-buffer')->assertSuccessful();

        $this->assertEquals(0, $post->fresh()->views_count);
    }

    #[Test]
    public function cache_is_cleared_when_post_is_updated(): void
    {
        // Aquece o cache
        $this->get('/')->assertOk();
        $this->assertEquals('HIT', $this->get('/')->headers->get('X-Cache-Status'));

        // Atualiza um post (dispara PostObserver que limpa o cache)
        $post = Post::factory()->published()->create();
        $post->update(['title' => 'Novo título']);

        // Cache deve ter sido invalidado
        $this->assertEquals('MISS', $this->get('/')->headers->get('X-Cache-Status'));
    }

    #[Test]
    public function cache_is_cleared_when_post_is_deleted(): void
    {
        $this->get('/')->assertOk();
        $this->assertEquals('HIT', $this->get('/')->headers->get('X-Cache-Status'));

        $post = Post::factory()->published()->create();
        $post->delete();

        $this->assertEquals('MISS', $this->get('/')->headers->get('X-Cache-Status'));
    }
}
