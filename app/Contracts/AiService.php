<?php

namespace App\Contracts;

use App\Models\AiComment;
use App\Models\Post;

interface AiService
{
    public function generateText(string $prompt): string;

    public function translateText(string $text): string;

    public function translateHtml(string $html): string;

    public function generateComment(Post $post): AiComment;
}
