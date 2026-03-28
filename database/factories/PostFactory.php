<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PostFactory extends Factory
{
    public function definition(): array
    {
        $title = $this->faker->sentence(6);

        return [
            'user_id'                => User::factory(),
            'title'                  => $title,
            'slug'                   => Str::slug($title) . '-' . Str::random(8),
            'content'                => $this->faker->paragraphs(3, true),
            'cover_image'            => null,
            'cover_image_prompt'     => null,
            'cover_image_use_content' => false,
            'cover_image_use_bio'    => false,
            'published_at'           => null,
            'views_count'            => 0,
        ];
    }

    public function published(): static
    {
        return $this->state(['published_at' => now()]);
    }
}
