<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\GeminiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateEnglishVersionJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;
    public int $tries   = 2;

    public function __construct(public readonly int $postId) {}

    public function handle(GeminiService $service): void
    {
        $post = Post::findOrFail($this->postId);
        $user = $post->user;

        $post->update(['content_en_status' => 'pending']);

        try {
            $titleEn   = $service->translateText($post->title, $user);
            $contentEn = $service->translateHtml($post->content, $user);

            $post->update([
                'title_en'          => $titleEn,
                'content_en'        => $contentEn,
                'content_en_status' => 'done',
            ]);

            if ($post->latestAiComment && ! $post->latestAiComment->content_en) {
                $post->latestAiComment->update([
                    'content_en' => $service->translateText($post->latestAiComment->content, $user),
                ]);
            }
        } catch (\Throwable $e) {
            $post->update(['content_en_status' => 'error']);
            throw $e;
        }
    }
}
