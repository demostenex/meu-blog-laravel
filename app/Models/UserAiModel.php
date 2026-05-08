<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAiModel extends Model
{
    protected $fillable = ['user_ai_provider_id', 'model', 'capability', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(UserAiProvider::class, 'user_ai_provider_id');
    }
}
