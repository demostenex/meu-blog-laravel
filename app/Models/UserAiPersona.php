<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserAiPersona extends Model
{
    protected $fillable = [
        'user_id', 'name', 'ai_name', 'content',
        'ai_photo', 'accent_color', 'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function providers(): HasMany
    {
        return $this->hasMany(UserAiProvider::class, 'persona_id');
    }
}
