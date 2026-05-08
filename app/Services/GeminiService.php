<?php

namespace App\Services;

use App\Models\AiComment;
use App\Models\Post;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GeminiService extends AbstractAiService
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly ?string $persona = null,
    ) {}

    public function generateText(string $prompt): string
    {
        $response = Http::when(app()->isLocal(), fn ($http) => $http->withoutVerifying())
            ->timeout(120)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
            ]);

        $response->throw();

        return $response->json('candidates.0.content.parts.0.text')
            ?? throw new \RuntimeException('Unexpected response from Gemini API.');
    }

    public function translateText(string $text): string
    {
        return $this->generateText(<<<PROMPT
Translate the following text to English. Preserve the meaning, tone, and paragraph structure. Return only the translated text, without any explanation or commentary.

{$text}
PROMPT);
    }

    public function generateComment(Post $post): AiComment
    {
        $text = $this->generateText($this->buildCommentPrompt($post));

        return $post->aiComments()->create([
            'content' => $text,
            'model'   => $this->model,
        ]);
    }

    protected function buildCommentPrompt(Post $post): string
    {
        $persona = $this->persona
            ?? 'Você é um crítico sarcástico e bem-humorado. Comente o artigo de forma irônica e espirituosa, mas sem ser ofensivo.';

        $articleText = Str::limit(strip_tags($post->content), 8000);

        $recentPosts = Post::published()
            ->where('id', '!=', $post->id)
            ->latest('published_at')
            ->limit(5)
            ->get(['title', 'content']);

        $memoryBlock = '';
        if ($recentPosts->isNotEmpty()) {
            $lines = $recentPosts->map(
                fn ($p) => '- "' . $p->title . '": ' . Str::limit(strip_tags($p->content), 300)
            )->join("\n");
            $memoryBlock = "\n\nOutros artigos do blog que você já leu (use para fazer conexões quando relevante):\n{$lines}\n";
        }

        return <<<PROMPT
{$persona}
{$memoryBlock}
Leia o artigo abaixo e faça um comentário curto (máximo 3 parágrafos) sobre ele com sua persona.

Título: {$post->title}

Conteúdo:
{$articleText}
PROMPT;
    }
}
