<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiServiceTranslateTest extends TestCase
{
    private function makeUser(): User
    {
        $user = new User();
        $user->gemini_api_key = encrypt('fake-api-key');
        $user->gemini_model   = 'gemini-2.0-flash';

        return $user;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function translate_text_calls_gemini_api_and_returns_translated_text(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Hello, world!']]]],
                ],
            ], 200),
        ]);

        $service    = new GeminiService();
        $translated = $service->translateText('Olá, mundo!', $this->makeUser());

        $this->assertSame('Hello, world!', $translated);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function translate_text_sends_english_translation_prompt(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Some translation']]]],
                ],
            ], 200),
        ]);

        $service = new GeminiService();
        $service->translateText('Texto de teste', $this->makeUser());

        Http::assertSent(function (Request $request) {
            $body = $request->data();
            $promptText = $body['contents'][0]['parts'][0]['text'] ?? '';

            return str_contains($promptText, 'English')
                && str_contains($promptText, 'Texto de teste');
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function translate_text_uses_user_model(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'translated']]]],
                ],
            ], 200),
        ]);

        $user              = $this->makeUser();
        $user->gemini_model = 'gemini-1.5-pro';

        $service = new GeminiService();
        $service->translateText('texto', $user);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'gemini-1.5-pro'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function translate_text_throws_on_unexpected_response_structure(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [],
            ], 200),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unexpected response from Gemini API.');

        (new GeminiService())->translateText('texto', $this->makeUser());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function translate_text_throws_on_api_error(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'bad key'], 400),
        ]);

        $this->expectException(\Illuminate\Http\Client\RequestException::class);

        (new GeminiService())->translateText('texto', $this->makeUser());
    }
}
