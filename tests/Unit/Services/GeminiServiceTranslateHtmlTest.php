<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiServiceTranslateHtmlTest extends TestCase
{
    private function makeUser(): User
    {
        $user = new User();
        $user->gemini_api_key = encrypt('fake-api-key');
        $user->gemini_model   = 'gemini-2.0-flash';

        return $user;
    }

    private function fakeGeminiResponse(string $text): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => $text]]]],
                ],
            ], 200),
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function translate_html_replaces_href_with_placeholder_before_sending(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Hello TRANSURL1']]]]],
            ], 200),
        ]);

        $service = new GeminiService();
        $service->translateHtml('<p>Olá <a href="https://example.com">link</a></p>', $this->makeUser());

        Http::assertSent(function (Request $r) {
            $promptText = $r->data()['contents'][0]['parts'][0]['text'] ?? '';
            return str_contains($promptText, 'TRANSURL1')
                && ! str_contains($promptText, 'https://example.com');
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function translate_html_restores_original_href_in_result(): void
    {
        $this->fakeGeminiResponse('<p>Hello <a href="TRANSURL1">link</a></p>');

        $service = new GeminiService();
        $result  = $service->translateHtml(
            '<p>Olá <a href="https://example.com/page">link</a></p>',
            $this->makeUser()
        );

        $this->assertStringContainsString('https://example.com/page', $result);
        $this->assertStringNotContainsString('TRANSURL1', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function translate_html_restores_original_src_in_result(): void
    {
        $this->fakeGeminiResponse('<img src="TRANSURL1" alt="imagem">');

        $service = new GeminiService();
        $result  = $service->translateHtml(
            '<img src="https://cdn.example.com/photo.jpg" alt="foto">',
            $this->makeUser()
        );

        $this->assertStringContainsString('https://cdn.example.com/photo.jpg', $result);
        $this->assertStringNotContainsString('TRANSURL1', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function translate_html_handles_multiple_links(): void
    {
        $this->fakeGeminiResponse(
            '<p><a href="TRANSURL1">first</a> and <a href="TRANSURL2">second</a></p>'
        );

        $service = new GeminiService();
        $result  = $service->translateHtml(
            '<p><a href="https://first.com">primeiro</a> e <a href="https://second.com">segundo</a></p>',
            $this->makeUser()
        );

        $this->assertStringContainsString('https://first.com', $result);
        $this->assertStringContainsString('https://second.com', $result);
        $this->assertStringNotContainsString('TRANSURL1', $result);
        $this->assertStringNotContainsString('TRANSURL2', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function translate_html_with_no_links_works_normally(): void
    {
        $this->fakeGeminiResponse('<p>Hello world</p>');

        $service = new GeminiService();
        $result  = $service->translateHtml('<p>Olá mundo</p>', $this->makeUser());

        $this->assertSame('<p>Hello world</p>', $result);
    }
}
