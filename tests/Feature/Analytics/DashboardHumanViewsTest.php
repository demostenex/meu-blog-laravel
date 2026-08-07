<?php

namespace Tests\Feature\Analytics;

use App\Models\PageView;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardHumanViewsTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function dashboard_uses_human_pageviews_for_post_totals_and_ranking(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $firstPost = Post::factory()->published()->create(['user_id' => $user->id]);
        $secondPost = Post::factory()->published()->create(['user_id' => $user->id]);
        $otherPost = Post::factory()->published()->create(['user_id' => $otherUser->id]);

        $this->recordPageViews("blog/{$firstPost->slug}", 2);
        $this->recordPageViews("blog/{$firstPost->slug}", 3, isBot: true);
        $this->recordPageViews("blog/{$secondPost->slug}", 1);
        $this->recordPageViews("blog/{$otherPost->slug}", 9);
        $this->recordPageViews('/', 5);

        $component = Volt::actingAs($user)->test('dashboard');
        $topPosts = $component->get('topPosts');

        $this->assertSame(3, $component->get('totalViews'));
        $this->assertSame($firstPost->id, $topPosts->first()->id);
        $this->assertSame(2, $topPosts->first()->human_views_count);
    }

    private function recordPageViews(string $path, int $count, bool $isBot = false): void
    {
        for ($i = 0; $i < $count; $i++) {
            PageView::create([
                'path' => $path,
                'referrer' => null,
                'device' => 'desktop',
                'ip_hash' => hash('sha256', "127.0.0.{$i}" . config('app.key')),
                'user_agent' => $isBot ? 'Googlebot/2.1' : 'Mozilla/5.0',
                'view_token' => fake()->uuid(),
                'is_bot' => $isBot,
                'created_at' => now(),
            ]);
        }
    }
}
