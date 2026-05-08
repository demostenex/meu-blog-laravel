<?php

namespace Tests\Unit\Services;

use App\Services\GeminiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiServiceTranslateTest extends TestCase
{
    private function makeService(string $model = 'gemini-2.0-flash'): GeminiService
    {
        return new GeminiService(apiKey: 'fake-api-key', model: $model);
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

        $translated = $this->makeService()->translateText('Olá, mundo!');

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

        $this->makeService()->translateText('Texto de teste');

        Http::assertSent(function (Request $request) {
            $body       = $request->data();
            $promptText = $body['contents'][0]['parts'][0]['text'] ?? '';

            return str_contains($promptText, 'English')
                && str_contains($promptText, 'Texto de teste');
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function translate_text_uses_configured_model(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'translated']]]],
                ],
            ], 200),
        ]);

        $this->makeService('gemini-1.5-pro')->translateText('texto');

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

        $this->makeService()->translateText('texto');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function translate_text_throws_on_api_error(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'bad key'], 400),
        ]);

        $this->expectException(\Illuminate\Http\Client\RequestException::class);

        $this->makeService()->translateText('texto');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function generate_text_returns_raw_prompt_response(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Resposta gerada']]]],
                ],
            ], 200),
        ]);

        $result = $this->makeService()->generateText('Prompt qualquer');

        $this->assertSame('Resposta gerada', $result);
    }
}
