<?php

namespace Tests\Feature\Commands;

use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FlushViewsBufferTest extends TestCase
{
    use DatabaseTransactions;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();
        $this->post = Post::factory()->create(['views_count' => 0]);
        Redis::del("post:views:{$this->post->id}");
    }

    protected function tearDown(): void
    {
        Redis::del("post:views:{$this->post->id}");
        parent::tearDown();
    }

    #[Test]
    public function increment_views_escreve_no_redis_e_nao_no_banco(): void
    {
        $this->post->incrementViews();
        $this->post->incrementViews();
        $this->post->incrementViews();

        $this->assertEquals(3, (int) Redis::get("post:views:{$this->post->id}"));
        $this->assertEquals(0, $this->post->fresh()->views_count);
    }

    #[Test]
    public function flush_move_contagem_do_redis_para_o_banco(): void
    {
        $this->post->incrementViews();
        $this->post->incrementViews();

        $this->artisan('app:flush-views-buffer')->assertSuccessful();

        $this->assertEquals(2, $this->post->fresh()->views_count);
        $this->assertNull(Redis::get("post:views:{$this->post->id}"));
    }

    #[Test]
    public function flush_acumula_sobre_views_existentes_no_banco(): void
    {
        $this->post->update(['views_count' => 10]);

        $this->post->incrementViews();
        $this->post->incrementViews();
        $this->post->incrementViews();

        $this->artisan('app:flush-views-buffer')->assertSuccessful();

        $this->assertEquals(13, $this->post->fresh()->views_count);
    }

    #[Test]
    public function flush_sem_buffer_informa_zero_posts(): void
    {
        $this->artisan('app:flush-views-buffer')
            ->assertSuccessful()
            ->expectsOutputToContain('0 post(s)');
    }

    #[Test]
    public function flush_nao_altera_updated_at_do_post(): void
    {
        $updatedAt = $this->post->updated_at;

        $this->post->incrementViews();
        $this->artisan('app:flush-views-buffer')->assertSuccessful();

        $this->assertEquals(
            $updatedAt->toDateTimeString(),
            $this->post->fresh()->updated_at->toDateTimeString()
        );
    }

    #[Test]
    public function login_dispara_flush_automaticamente(): void
    {
        $user = User::factory()->create();

        $this->post->incrementViews();
        $this->post->incrementViews();

        event(new Login('web', $user, false));

        $this->assertEquals(2, $this->post->fresh()->views_count);
        $this->assertNull(Redis::get("post:views:{$this->post->id}"));
    }
}
