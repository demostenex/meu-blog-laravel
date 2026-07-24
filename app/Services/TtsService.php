<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TtsService
{
    /**
     * Vozes pré-definidas do Gemini TTS (subconjunto curado das ~30 disponíveis),
     * com um descritor curto do timbre pra ajudar na escolha na UI.
     */
    public const VOICES = [
        'Kore' => 'Firme',
        'Puck' => 'Animada',
        'Charon' => 'Informativa',
        'Fenrir' => 'Assertiva',
        'Aoede' => 'Arejada',
        'Leda' => 'Jovem',
        'Orus' => 'Firme',
        'Zephyr' => 'Brilhante',
        'Autonoe' => 'Brilhante',
        'Enceladus' => 'Sussurrada',
    ];

    /**
     * Tamanho alvo de cada pedaço de texto por chamada de TTS. A API tem um limite prático
     * de saída de áudio por chamada — textos maiores que isso simplesmente cortam no meio.
     */
    private const CHUNK_SIZE = 5500;

    /**
     * Cap total de caracteres considerados pra narração. Cobre a grande maioria dos posts
     * inteiros; além disso, o tempo total de geração (uma chamada de TTS por pedaço, em
     * série) fica excessivo. ~60000 chars ≈ 10-11 pedaços ≈ um post de ~10 mil palavras.
     */
    private const MAX_TOTAL_CHARS = 60000;

    /**
     * Tentativas por pedaço antes de desistir (não confundir com o $tries do job, que
     * cobriria o processo inteiro — aqui é só o trecho atual, pra não perder o trabalho
     * já feito nos pedaços anteriores por causa de uma falha isolada).
     */
    private const CHUNK_ATTEMPTS = 2;

    public function __construct(private readonly AiServiceFactory $factory) {}

    public function generateAudio(Post $post, User $user): string
    {
        $provider = $user->aiProviders()
            ->with('models')
            ->where('provider', 'gemini')
            ->first();

        if (! $provider?->api_key) {
            throw new \RuntimeException('Nenhum provider Gemini configurado para este usuário.');
        }

        $model = $this->factory->audioModelFor($provider);
        $voice = array_key_exists($post->audio_voice ?? '', self::VOICES) ? $post->audio_voice : 'Kore';

        $fullText = Str::limit(trim($post->title."\n\n".strip_tags($post->content)), self::MAX_TOTAL_CHARS, '');
        $chunks = $this->splitIntoChunks($fullText, self::CHUNK_SIZE);

        $pcmChunks = [];
        foreach ($chunks as $chunk) {
            $pcmChunks[] = $this->synthesizeChunk($chunk, $model, $voice, $provider->api_key);
        }

        return app(AudioService::class)->storeConcatenatedPcmAsWav($pcmChunks, 'post-audio');
    }

    private function synthesizeChunk(string $text, string $model, string $voice, string $apiKey): string
    {
        $prompt = "Leia o texto a seguir em um ritmo natural, calmo e bem pausado, como um narrador de podcast, sem pressa:\n\n{$text}";

        $lastException = null;

        for ($attempt = 1; $attempt <= self::CHUNK_ATTEMPTS; $attempt++) {
            try {
                $response = Http::when(app()->isLocal(), fn ($http) => $http->withoutVerifying())
                    ->timeout(300)
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                        'contents' => [['parts' => [['text' => $prompt]]]],
                        'generationConfig' => [
                            'responseModalities' => ['AUDIO'],
                            'speechConfig' => [
                                'voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => $voice]],
                            ],
                        ],
                    ]);

                $response->throw();

                $parts = $response->json('candidates.0.content.parts') ?? [];

                foreach ($parts as $part) {
                    if (isset($part['inlineData']['data'])) {
                        return $part['inlineData']['data'];
                    }
                }

                throw new \RuntimeException('Nenhum áudio retornado pela API do Gemini.');
            } catch (\Throwable $e) {
                $lastException = $e;
            }
        }

        throw $lastException;
    }

    /**
     * Quebra o texto em pedaços de até $maxLength chars, preferindo cortar em parágrafos
     * e, se um parágrafo sozinho ainda for grande demais, em frases — evita cortar no meio
     * de uma palavra ou frase, o que soaria estranho entre um pedaço de áudio e o próximo.
     *
     * @return string[]
     */
    private function splitIntoChunks(string $text, int $maxLength): array
    {
        $chunks = [];
        $current = '';

        foreach (preg_split('/\n{2,}/', $text) as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            if (strlen($paragraph) > $maxLength) {
                foreach ($this->splitBySentence($paragraph, $maxLength) as $piece) {
                    [$current, $chunks] = $this->appendPiece($current, $piece, $maxLength, $chunks);
                }

                continue;
            }

            [$current, $chunks] = $this->appendPiece($current, $paragraph, $maxLength, $chunks);
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    private function splitBySentence(string $paragraph, int $maxLength): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph) ?: [$paragraph];
        $pieces = [];
        $current = '';

        foreach ($sentences as $sentence) {
            if (strlen($current) + strlen($sentence) + 1 > $maxLength && $current !== '') {
                $pieces[] = $current;
                $current = '';
            }
            $current .= ($current === '' ? '' : ' ').$sentence;
        }

        if ($current !== '') {
            $pieces[] = $current;
        }

        return $pieces;
    }

    private function appendPiece(string $current, string $piece, int $maxLength, array $chunks): array
    {
        if (strlen($current) + strlen($piece) + 2 > $maxLength && $current !== '') {
            $chunks[] = $current;
            $current = '';
        }

        $current .= ($current === '' ? '' : "\n\n").$piece;

        return [$current, $chunks];
    }
}
