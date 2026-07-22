<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'post_id' => null,
            'title' => ucwords($this->faker->words(3, true)),
            'path' => 'documents/'.Str::random(20).'.pdf',
            'original_filename' => $this->faker->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => $this->faker->numberBetween(1024, 5_000_000),
        ];
    }
}
