<?php

namespace Tests\Unit\Models;

use App\Models\Post;
use Tests\TestCase;

class PostReadingTimeTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function reading_time_returns_one_for_empty_content(): void
    {
        $post = new Post(['content' => '']);

        $this->assertSame(1, $post->reading_time);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function reading_time_returns_one_for_short_content(): void
    {
        $post = new Post(['content' => str_repeat('palavra ', 100)]);

        $this->assertSame(1, $post->reading_time);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function reading_time_calculates_correctly_for_200_words(): void
    {
        $post = new Post(['content' => str_repeat('palavra ', 200)]);

        $this->assertSame(1, $post->reading_time);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function reading_time_rounds_up_to_next_minute(): void
    {
        $post = new Post(['content' => str_repeat('palavra ', 201)]);

        $this->assertSame(2, $post->reading_time);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function reading_time_ignores_html_tags(): void
    {
        $words = str_repeat('palavra ', 200);
        $post  = new Post(['content' => "<p>{$words}</p><strong>extra</strong>"]);

        $this->assertSame(1, $post->reading_time);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function reading_time_calculates_for_long_article(): void
    {
        // 600 palavras => 3 minutos
        $post = new Post(['content' => str_repeat('palavra ', 600)]);

        $this->assertSame(3, $post->reading_time);
    }
}
