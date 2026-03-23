<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use App\Models\AiComment;
use Illuminate\Support\Facades\Http;

class GeminiService
{
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

        $response = Http::withoutVerifying()
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
