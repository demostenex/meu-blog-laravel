<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['user_id', 'category_id', 'title', 'title_en', 'slug', 'cover_image', 'cover_image_prompt', 'cover_image_use_content', 'cover_image_use_bio', 'content', 'content_en', 'content_en_status', 'published_at', 'views_count'])]
class Post extends Model
{
    use HasFactory;

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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
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

    public function getReadingTimeAttribute(): int
    {
        $wordCount = str_word_count(strip_tags($this->content ?? ''));

        return max(1, (int) ceil($wordCount / 200));
    }
}
