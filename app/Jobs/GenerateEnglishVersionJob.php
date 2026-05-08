<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\AiServiceFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateEnglishVersionJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;
    public int $tries   = 2;

    public function __construct(public readonly int $postId) {}

    public function handle(AiServiceFactory $factory): void
    {
        $post = Post::findOrFail($this->postId);
        $user = $post->user;

        if ($post->content_en_locked) {
            $post->update(['content_en_status' => 'done']);
            return;
        }

        $post->update(['content_en_status' => 'pending']);

        try {
            $service = $factory->for($user);

            $titleEn   = $service->translateText($post->title);
            $contentEn = $service->translateHtml($post->content);

            $post->update([
                'title_en'          => $titleEn,
                'content_en'        => $contentEn,
                'content_en_status' => 'done',
                'content_en_error'  => null,
            ]);

            if ($post->latestAiComment && ! $post->latestAiComment->content_en) {
                $post->latestAiComment->update([
                    'content_en' => $service->translateText($post->latestAiComment->content),
                ]);
            }
        } catch (\Throwable $e) {
            $post->update([
                'content_en_status' => 'error',
                'content_en_error'  => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
