<?php

namespace Tests\Unit\Services;

use App\Services\GeminiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiServiceTranslateHtmlTest extends TestCase
{
    private function makeService(): GeminiService
    {
        return new GeminiService(apiKey: 'fake-api-key', model: 'gemini-2.0-flash');
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

        $this->makeService()->translateHtml('<p>Olá <a href="https://example.com">link</a></p>');

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

        $result = $this->makeService()->translateHtml(
            '<p>Olá <a href="https://example.com/page">link</a></p>'
        );

        $this->assertStringContainsString('https://example.com/page', $result);
        $this->assertStringNotContainsString('TRANSURL1', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function translate_html_restores_original_src_in_result(): void
    {
        $this->fakeGeminiResponse('<img src="TRANSURL1" alt="imagem">');

        $result = $this->makeService()->translateHtml(
            '<img src="https://cdn.example.com/photo.jpg" alt="foto">'
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

        $result = $this->makeService()->translateHtml(
            '<p><a href="https://first.com">primeiro</a> e <a href="https://second.com">segundo</a></p>'
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

        $result = $this->makeService()->translateHtml('<p>Olá mundo</p>');

        $this->assertSame('<p>Hello world</p>', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function translate_html_handles_single_quoted_src(): void
    {
        $this->fakeGeminiResponse('<img src="TRANSURL1" alt="photo">');

        $result = $this->makeService()->translateHtml(
            "<img src='https://cdn.example.com/photo.jpg' alt='foto'>"
        );

        $this->assertStringContainsString('https://cdn.example.com/photo.jpg', $result);
        $this->assertStringNotContainsString('TRANSURL1', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function translate_html_handles_video_element(): void
    {
        $this->fakeGeminiResponse('<video src="TRANSURL1" controls></video>');

        $result = $this->makeService()->translateHtml(
            '<video src="https://cdn.example.com/video.mp4" controls></video>'
        );

        $this->assertStringContainsString('https://cdn.example.com/video.mp4', $result);
        $this->assertStringNotContainsString('TRANSURL1', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function translate_html_handles_data_src(): void
    {
        $this->fakeGeminiResponse('<img data-src="TRANSURL1" alt="lazy">');

        $result = $this->makeService()->translateHtml(
            '<img data-src="https://cdn.example.com/lazy.jpg" alt="preguiçosa">'
        );

        $this->assertStringContainsString('https://cdn.example.com/lazy.jpg', $result);
        $this->assertStringNotContainsString('TRANSURL1', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function translate_html_handles_iframe_embed(): void
    {
        $this->fakeGeminiResponse('<iframe src="TRANSURL1"></iframe>');

        $result = $this->makeService()->translateHtml(
            '<iframe src="https://www.youtube.com/embed/abc123"></iframe>'
        );

        $this->assertStringContainsString('https://www.youtube.com/embed/abc123', $result);
        $this->assertStringNotContainsString('TRANSURL1', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function translate_html_does_not_send_real_urls_to_gemini(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'video TRANSURL1 e imagem TRANSURL2']]]]],
            ], 200),
        ]);

        $this->makeService()->translateHtml(
            '<video src="https://cdn.example.com/video.mp4"></video><img src=\'https://cdn.example.com/img.jpg\'>'
        );

        Http::assertSent(function (Request $r) {
            $prompt = $r->data()['contents'][0]['parts'][0]['text'] ?? '';
            return str_contains($prompt, 'TRANSURL1')
                && str_contains($prompt, 'TRANSURL2')
                && ! str_contains($prompt, 'https://cdn.example.com');
        });
    }
}
