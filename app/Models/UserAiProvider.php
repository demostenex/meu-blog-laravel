<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserAiProvider extends Model
{
    protected $fillable = [
        'user_id', 'provider', 'api_key', 'persona_id', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'api_key'    => 'encrypted',
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(UserAiPersona::class);
    }

    public function models(): HasMany
    {
        return $this->hasMany(UserAiModel::class);
    }

    public function defaultModel(?string $capability = 'text'): HasOne
    {
        return $this->hasOne(UserAiModel::class)
            ->where('capability', $capability)
            ->where('is_default', true);
    }

    public static function knownProviders(): array
    {
        return [
            'gemini'    => 'Google Gemini',
            'openai'    => 'OpenAI',
            'anthropic' => 'Anthropic Claude',
        ];
    }

    public static function knownModels(string $provider, string $capability = 'text'): array
    {
        $map = [
            'gemini' => [
                'text'  => ['gemini-2.5-flash-preview-05-20', 'gemini-2.0-flash', 'gemini-2.0-flash-lite', 'gemini-1.5-pro'],
                'image' => ['gemini-3.1-flash-image-preview', 'imagen-3.0-generate-002'],
            ],
            'openai' => [
                'text'  => ['gpt-4.1', 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo'],
                'image' => ['dall-e-3', 'dall-e-2'],
            ],
            'anthropic' => [
                'text'  => ['claude-opus-4-7', 'claude-sonnet-4-6', 'claude-haiku-4-5-20251001'],
                'image' => [],
            ],
        ];

        return $map[$provider][$capability] ?? [];
    }
}
