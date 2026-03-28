<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'title', 'slug', 'cover_image', 'cover_image_prompt', 'cover_image_use_content', 'cover_image_use_bio', 'content', 'published_at', 'views_count'])]
class Post extends Model
{
    protected $casts = [
        'published_at'           => 'datetime',
        'cover_image_use_content' => 'boolean',
        'cover_image_use_bio'     => 'boolean',
    ];

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at');
    }
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

    public function incrementViews(): void
    {
        static::withoutTimestamps(fn() => $this->increment('views_count'));
    }
}
