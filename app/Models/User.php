<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'profile_photo_path', 'about_me', 'blog_description', 'social_x', 'social_instagram', 'social_facebook', 'social_linkedin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function posts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function aiProviders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserAiProvider::class);
    }

    public function defaultAiProvider(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserAiProvider::class)->where('is_default', true);
    }

    public function aiPersonas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserAiPersona::class);
    }

    public function defaultAiPersona(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserAiPersona::class)->where('is_default', true);
    }

    public function getRouteKey(): string
    {
        return \Illuminate\Support\Str::slug($this->name);
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::first();
    }
}
