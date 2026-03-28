<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImagenService
{
    private const MODEL = 'gemini-3.1-flash-image-preview';

    public function generateCoverImage(Post $post, User $user): string
    {
        $apiKey = $user->gemini_api_key;
        $prompt = $this->buildPrompt($post, $user);

        $response = Http::withoutVerifying()
            ->timeout(120)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/" . self::MODEL . ":generateContent?key={$apiKey}", [
                'contents'         => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['responseModalities' => ['TEXT', 'IMAGE']],
            ]);

        $response->throw();

        $parts = $response->json('candidates.0.content.parts') ?? [];

        foreach ($parts as $part) {
            if (isset($part['inlineData']['data'])) {
                return app(ImageService::class)->storeFromBase64(
                    $part['inlineData']['data'],
                    'covers'
                );
            }
        }

        throw new \RuntimeException('Nenhuma imagem retornada pelo Nano Banana. Tente ajustar o prompt.');
    }

    private function buildPrompt(Post $post, User $user): string
    {
        $parts = [trim($post->cover_image_prompt)];

        if ($post->cover_image_use_content) {
            $content = Str::limit(strip_tags($post->content ?? ''), 500);
            if ($content) {
                $parts[] = "Contexto do artigo \"{$post->title}\": {$content}";
            }
        }

        if ($post->cover_image_use_bio && $user->about_me) {
            $parts[] = "Sobre o autor do blog: {$user->about_me}";
        }

        $parts[] = 'Estilo: fotografia profissional, alta qualidade, adequada para blog, formato 16:9.';

        return implode("\n\n", $parts);
    }
}
