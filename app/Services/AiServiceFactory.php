<?php

namespace App\Services;

use App\Contracts\AiService;
use App\Models\User;
use App\Models\UserAiProvider;

class AiServiceFactory
{
    public function for(User $user): AiService
    {
        $provider = $user->aiProviders()
            ->with(['persona', 'models'])
            ->where('is_default', true)
            ->first()
            ?? $user->aiProviders()->with(['persona', 'models'])->first();

        if (! $provider) {
            throw new \RuntimeException("Usuário {$user->id} não tem nenhum AI provider configurado.");
        }

        return $this->make($provider);
    }

    public function make(UserAiProvider $provider, string $capability = 'text'): AiService
    {
        $model = $provider->models()
            ->where('capability', $capability)
            ->where('is_default', true)
            ->value('model')
            ?? $provider->models()->where('capability', $capability)->value('model')
            ?? $this->defaultModelFor($provider->provider, $capability);

        $persona  = $provider->persona?->content;

        return match ($provider->provider) {
            'gemini' => new GeminiService($provider->api_key, $model, $persona),
            default  => throw new \InvalidArgumentException("Provider não suportado: {$provider->provider}"),
        };
    }

    public function imageModelFor(UserAiProvider $provider): string
    {
        return $provider->models()
            ->where('capability', 'image')
            ->where('is_default', true)
            ->value('model')
            ?? $provider->models()->where('capability', 'image')->value('model')
            ?? $this->defaultModelFor($provider->provider, 'image');
    }

    private function defaultModelFor(string $provider, string $capability = 'text'): string
    {
        return match ([$provider, $capability]) {
            ['gemini', 'text']    => 'gemini-2.0-flash',
            ['gemini', 'image']   => 'gemini-3.1-flash-image-preview',
            ['openai', 'text']    => 'gpt-4o',
            ['openai', 'image']   => 'dall-e-3',
            ['anthropic', 'text'] => 'claude-sonnet-4-6',
            default               => throw new \InvalidArgumentException("Provider/capability não suportado: {$provider}/{$capability}"),
        };
    }
}
