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
        // Cap generoso o bastante pra ler o post inteiro na maioria dos casos, mas que evita
        // narrações de dezenas de minutos — cujo áudio bruto (PCM) estoura o memory_limit do
        // PHP ao ser decodificado/montado em memória (ver AudioService::storePcmAsWav).
        $text = Str::limit(trim($post->title."\n\n".strip_tags($post->content)), 8000, '');
        $voice = array_key_exists($post->audio_voice ?? '', self::VOICES) ? $post->audio_voice : 'Kore';

        $response = Http::when(app()->isLocal(), fn ($http) => $http->withoutVerifying())
            ->timeout(300)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$provider->api_key}", [
                'contents' => [['parts' => [['text' => $text]]]],
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
                return app(AudioService::class)->storePcmAsWav($part['inlineData']['data'], 'post-audio');
            }
        }

        throw new \RuntimeException('Nenhum áudio retornado pela API do Gemini.');
    }
}
