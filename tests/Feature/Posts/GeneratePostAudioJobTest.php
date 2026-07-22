<?php

namespace Tests\Feature\Posts;

use App\Jobs\GeneratePostAudioJob;
use App\Models\Post;
use App\Models\User;
use App\Services\TtsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GeneratePostAudioJobTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.image_disk' => 'public']);
        Storage::fake('public');

        $this->user = User::factory()->create();

        $provider = $this->user->aiProviders()->create([
            'provider' => 'gemini',
            'api_key' => 'fake-key',
            'is_default' => true,
        ]);
        $provider->models()->create(['model' => 'gemini-2.5-flash-preview-tts', 'capability' => 'audio', 'is_default' => true]);
    }

    private function mockTtsService(): TtsService
    {
        return $this->mock(TtsService::class);
    }

    // ── Dispatch via edit page ────────────────────────────────────────────

    #[Test]
    public function edit_page_dispatches_job(): void
    {
        Queue::fake();

        $post = Post::factory()->create(['user_id' => $this->user->id]);

        Volt::actingAs($this->user)
            ->test('posts.edit', ['post' => $post])
            ->call('generateAudio');

        Queue::assertPushed(GeneratePostAudioJob::class, function ($job) use ($post) {
            return $job->postId === $post->id;
        });
    }

    #[Test]
    public function edit_page_sets_status_to_pending_after_dispatch(): void
    {
        Queue::fake();

        $post = Post::factory()->create(['user_id' => $this->user->id]);

        Volt::actingAs($this->user)
            ->test('posts.edit', ['post' => $post])
            ->call('generateAudio');

        $this->assertSame('pending', $post->fresh()->audio_status);
    }

    #[Test]
    public function edit_page_saves_selected_voice_before_dispatching(): void
    {
        Queue::fake();

        $post = Post::factory()->create(['user_id' => $this->user->id]);

        Volt::actingAs($this->user)
            ->test('posts.edit', ['post' => $post])
            ->set('audioVoice', 'Puck')
            ->call('generateAudio');

        $this->assertSame('Puck', $post->fresh()->audio_voice);
    }

    #[Test]
    public function edit_page_falls_back_to_kore_for_an_unknown_voice(): void
    {
        Queue::fake();

        $post = Post::factory()->create(['user_id' => $this->user->id]);

        Volt::actingAs($this->user)
            ->test('posts.edit', ['post' => $post])
            ->set('audioVoice', 'NotARealVoice')
            ->call('generateAudio');

        $this->assertSame('Kore', $post->fresh()->audio_voice);
    }

    #[Test]
    public function edit_page_does_not_dispatch_when_no_provider_configured(): void
    {
        Queue::fake();

        $user = User::factory()->create(); // sem provider
        $post = Post::factory()->create(['user_id' => $user->id]);

        Volt::actingAs($user)
            ->test('posts.edit', ['post' => $post])
            ->call('generateAudio')
            ->assertSet('audioStatus', 'error:Configure um AI provider nas configurações de IA primeiro.');

        Queue::assertNothingPushed();
    }

    // ── Job execution ─────────────────────────────────────────────────────

    #[Test]
    public function job_stores_audio_path_and_sets_status_done(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'audio_status' => 'pending',
        ]);

        $this->mockTtsService()
            ->shouldReceive('generateAudio')
            ->once()
            ->andReturn('post-audio/fake.wav');

        (new GeneratePostAudioJob($post->id))->handle(app(TtsService::class));

        $post->refresh();

        $this->assertSame('post-audio/fake.wav', $post->audio_path);
        $this->assertSame('done', $post->audio_status);
        $this->assertNull($post->audio_error);
        $this->assertNotNull($post->audio_generated_at);
    }

    #[Test]
    public function job_deletes_previous_audio_file_when_regenerating(): void
    {
        Storage::disk('public')->put('post-audio/old.wav', 'old-content');

        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'audio_path' => 'post-audio/old.wav',
            'audio_status' => 'done',
        ]);

        $this->mockTtsService()
            ->shouldReceive('generateAudio')
            ->once()
            ->andReturn('post-audio/new.wav');

        (new GeneratePostAudioJob($post->id))->handle(app(TtsService::class));

        Storage::disk('public')->assertMissing('post-audio/old.wav');
        $this->assertSame('post-audio/new.wav', $post->fresh()->audio_path);
    }

    #[Test]
    public function job_sets_status_error_and_rethrows_on_api_failure(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'audio_status' => 'pending',
        ]);

        $this->mockTtsService()
            ->shouldReceive('generateAudio')
            ->once()
            ->andThrow(new \RuntimeException('API quota exceeded.'));

        $this->expectException(\RuntimeException::class);

        (new GeneratePostAudioJob($post->id))->handle(app(TtsService::class));
    }

    #[Test]
    public function job_saves_error_message_on_failure(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'audio_status' => 'pending',
        ]);

        $this->mockTtsService()
            ->shouldReceive('generateAudio')
            ->once()
            ->andThrow(new \RuntimeException('API quota exceeded.'));

        try {
            (new GeneratePostAudioJob($post->id))->handle(app(TtsService::class));
        } catch (\RuntimeException) {
        }

        $post->refresh();
        $this->assertSame('error', $post->audio_status);
        $this->assertSame('API quota exceeded.', $post->audio_error);
    }

    #[Test]
    public function job_saves_error_messages_longer_than_255_characters(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'audio_status' => 'pending',
        ]);

        $longMessage = 'cURL error 28: Operation timed out after 300001 milliseconds with 0 bytes received (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro-preview-tts:generateContent?key=fake-key-with-extra-padding-to-exceed-255-chars';
        $this->assertGreaterThan(255, strlen($longMessage));

        $this->mockTtsService()
            ->shouldReceive('generateAudio')
            ->once()
            ->andThrow(new \RuntimeException($longMessage));

        try {
            (new GeneratePostAudioJob($post->id))->handle(app(TtsService::class));
        } catch (\RuntimeException) {
        }

        $post->refresh();
        $this->assertSame('error', $post->audio_status);
        $this->assertSame($longMessage, $post->audio_error);
    }

    // ── Polling status via edit page ──────────────────────────────────────

    #[Test]
    public function refresh_audio_status_sets_success_when_done(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'audio_status' => 'done',
            'audio_path' => 'post-audio/fake.wav',
        ]);

        Volt::actingAs($this->user)
            ->test('posts.edit', ['post' => $post])
            ->call('refreshAudioStatus')
            ->assertSet('audioStatus', 'success');
    }

    #[Test]
    public function refresh_audio_status_shows_real_error_message(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'audio_status' => 'error',
            'audio_error' => 'API quota exceeded.',
        ]);

        Volt::actingAs($this->user)
            ->test('posts.edit', ['post' => $post])
            ->call('refreshAudioStatus')
            ->assertSet('audioStatus', 'error:API quota exceeded.');
    }

    // ── Exibição pública e no admin ─────────────────────────────────────────

    #[Test]
    public function public_page_shows_audio_player_only_when_audio_path_present(): void
    {
        $withAudio = Post::factory()->create([
            'user_id' => $this->user->id,
            'published_at' => now(),
            'audio_path' => 'post-audio/fake.wav',
        ]);
        $withoutAudio = Post::factory()->create([
            'user_id' => $this->user->id,
            'published_at' => now(),
        ]);

        $this->get(route('posts.show', $withAudio->slug))
            ->assertOk()
            ->assertSee('<audio', false);

        $this->get(route('posts.show', $withoutAudio->slug))
            ->assertOk()
            ->assertDontSee('<audio', false);
    }

    #[Test]
    public function admin_list_shows_audio_badge_when_status_done(): void
    {
        Post::factory()->create([
            'user_id' => $this->user->id,
            'audio_status' => 'done',
        ]);

        Volt::actingAs($this->user)
            ->test('posts.index')
            ->assertSee('🔊');
    }
}
