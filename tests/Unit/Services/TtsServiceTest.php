<?php

namespace Tests\Unit\Services;

use App\Models\Post;
use App\Models\User;
use App\Services\TtsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TtsServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.image_disk' => 'public']);
        Storage::fake('public');
    }

    private function makeUserWithGeminiProvider(): User
    {
        $user = User::factory()->create();

        $provider = $user->aiProviders()->create([
            'provider' => 'gemini',
            'api_key' => 'fake-api-key',
            'is_default' => true,
        ]);
        $provider->models()->create(['model' => 'gemini-2.5-flash-preview-tts', 'capability' => 'audio', 'is_default' => true]);

        return $user;
    }

    private function fakeAudioResponse(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['inlineData' => ['data' => base64_encode('fake-pcm-bytes')]]]]],
                ],
            ], 200),
        ]);
    }

    #[Test]
    public function generate_audio_sends_the_posts_selected_voice(): void
    {
        $this->fakeAudioResponse();
        $user = $this->makeUserWithGeminiProvider();
        $post = Post::factory()->create(['user_id' => $user->id, 'audio_voice' => 'Puck']);

        app(TtsService::class)->generateAudio($post, $user);

        Http::assertSent(function (Request $request) {
            return $request['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName'] === 'Puck';
        });
    }

    #[Test]
    public function generate_audio_falls_back_to_kore_when_voice_is_not_set(): void
    {
        $this->fakeAudioResponse();
        $user = $this->makeUserWithGeminiProvider();
        $post = Post::factory()->create(['user_id' => $user->id, 'audio_voice' => null]);

        app(TtsService::class)->generateAudio($post, $user);

        Http::assertSent(function (Request $request) {
            return $request['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName'] === 'Kore';
        });
    }

    #[Test]
    public function generate_audio_falls_back_to_kore_for_an_unknown_stored_voice(): void
    {
        $this->fakeAudioResponse();
        $user = $this->makeUserWithGeminiProvider();
        $post = Post::factory()->create(['user_id' => $user->id, 'audio_voice' => 'NotARealVoice']);

        app(TtsService::class)->generateAudio($post, $user);

        Http::assertSent(function (Request $request) {
            return $request['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName'] === 'Kore';
        });
    }

    #[Test]
    public function generate_audio_prepends_a_pacing_instruction_to_the_text(): void
    {
        $this->fakeAudioResponse();
        $user = $this->makeUserWithGeminiProvider();
        $post = Post::factory()->create(['user_id' => $user->id, 'content' => '<p>Um texto curto.</p>']);

        app(TtsService::class)->generateAudio($post, $user);

        Http::assertSent(function (Request $request) {
            return str_contains($request['contents'][0]['parts'][0]['text'], 'ritmo natural, calmo e bem pausado');
        });
    }

    #[Test]
    public function generate_audio_splits_long_content_into_multiple_synthesis_calls(): void
    {
        $this->fakeAudioResponse();
        $user = $this->makeUserWithGeminiProvider();
        $longContent = str_repeat('Esta é uma frase de teste bem repetitiva pra forçar múltiplos pedaços de áudio. ', 250);
        $post = Post::factory()->create(['user_id' => $user->id, 'content' => "<p>{$longContent}</p>"]);

        app(TtsService::class)->generateAudio($post, $user);

        $this->assertGreaterThan(1, count(Http::recorded()));
    }

    #[Test]
    public function generate_audio_retries_a_failed_chunk_before_giving_up(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['message' => 'temporary failure']], 500)
                ->push([
                    'candidates' => [
                        ['content' => ['parts' => [['inlineData' => ['data' => base64_encode('fake-pcm-bytes')]]]]],
                    ],
                ], 200),
        ]);

        $user = $this->makeUserWithGeminiProvider();
        $post = Post::factory()->create(['user_id' => $user->id, 'content' => '<p>Curto.</p>']);

        $path = app(TtsService::class)->generateAudio($post, $user);

        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);
    }

    #[Test]
    public function generate_audio_throws_after_exhausting_chunk_retries(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'persistent failure']], 500),
        ]);

        $user = $this->makeUserWithGeminiProvider();
        $post = Post::factory()->create(['user_id' => $user->id, 'content' => '<p>Curto.</p>']);

        $this->expectException(RequestException::class);

        app(TtsService::class)->generateAudio($post, $user);
    }
}
