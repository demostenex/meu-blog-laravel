<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'title', 'slug', 'cover_image', 'content'])]
class Post extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aiComments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AiComment::class);
    }

    public function latestAiComment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AiComment::class)->latestOfMany();
    }
}
