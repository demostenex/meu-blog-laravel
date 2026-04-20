<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use App\Models\AiComment;
use Illuminate\Support\Facades\Http;

class GeminiService
{
    public function translateText(string $text, User $user): string
    {
        $model  = $user->gemini_model ?? 'gemini-2.0-flash';
        $apiKey = $user->gemini_api_key;

        $prompt = <<<PROMPT
Translate the following text to English. Preserve the meaning, tone, and paragraph structure. Return only the translated text, without any explanation or commentary.

{$text}
PROMPT;

        $response = Http::when(app()->isLocal(), fn ($http) => $http->withoutVerifying())
            ->timeout(120)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
            ]);

        $response->throw();

        return $response->json('candidates.0.content.parts.0.text')
            ?? throw new \RuntimeException('Unexpected response from Gemini API.');
    }

    /**
     * Translates HTML content to English while preserving all URLs (href/src).
     * URLs are replaced with numbered placeholders before sending to the API
     * and restored afterwards, so the AI never sees or modifies real links.
     */
    public function translateHtml(string $html, User $user): string
    {
        $urls    = [];
        $counter = 0;

        // Replace every href and src value with a placeholder
        $withPlaceholders = preg_replace_callback(
            '/((?:href|src|data-src)=")([^"]+)(")/i',
            function ($m) use (&$urls, &$counter) {
                $counter++;
                $key         = "TRANSURL{$counter}";
                $urls[$key]  = $m[2];

                return $m[1] . $key . $m[3];
            },
            $html
        );

        $translated = $this->translateText($withPlaceholders, $user);

        // Restore original URLs
        foreach ($urls as $key => $url) {
            $translated = str_replace($key, $url, $translated);
        }

        return $translated;
    }

    public function generateComment(Post $post, User $user): AiComment
    {
        $persona = $post->user->gemini_persona
            ?? 'Você é um crítico sarcástico e bem-humorado. Comente o artigo de forma irônica e espirituosa, mas sem ser ofensivo.';

        $model = $post->user->gemini_model ?? 'gemini-2.0-flash';
        $apiKey = $post->user->gemini_api_key;

        $articleText = \Str::limit(strip_tags($post->content), 8000);

        $prompt = <<<PROMPT
{$persona}

Leia o artigo abaixo e faça um comentário curto (máximo 3 parágrafos) sobre ele com sua persona.

Título: {$post->title}

Conteúdo:
{$articleText}
PROMPT;

        $response = Http::when(app()->isLocal(), fn ($http) => $http->withoutVerifying())
            ->timeout(120)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
            ]);

        $response->throw();

        $text = $response->json('candidates.0.content.parts.0.text')
            ?? throw new \RuntimeException('Resposta inesperada da API do Gemini.');

        return $post->aiComments()->create([
            'content' => $text,
            'model'   => $model,
        ]);
    }
}
