<?php

namespace Tests\Unit\Services;

use App\Models\Post;
use App\Models\User;
use App\Services\TtsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
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
}
