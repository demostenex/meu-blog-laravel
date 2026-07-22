<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\TtsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GeneratePostAudioJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 360;

    public int $tries = 2;

    public function __construct(public readonly int $postId) {}

    public function handle(TtsService $ttsService): void
    {
        $post = Post::findOrFail($this->postId);
        $user = $post->user;

        $post->update(['audio_status' => 'pending']);

        try {
            if ($post->audio_path) {
                Storage::disk(config('filesystems.image_disk', 'public'))->delete($post->audio_path);
            }

            $path = $ttsService->generateAudio($post, $user);

            $post->update([
                'audio_path' => $path,
                'audio_status' => 'done',
                'audio_error' => null,
                'audio_generated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $post->update([
                'audio_status' => 'error',
                'audio_error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
