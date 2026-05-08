<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class FlushViewsBuffer extends Command
{
    protected $signature = 'app:flush-views-buffer';

    protected $description = 'Drena o buffer de visualizações do Redis para o banco de dados';

    public function handle(): int
    {
        $flushed = 0;

        Post::pluck('id')->each(function (int $postId) use (&$flushed) {
            $count = (int) (Redis::getdel("post:views:{$postId}") ?? 0);

            if ($count <= 0) {
                return;
            }

            Post::withoutTimestamps(
                fn () => Post::where('id', $postId)->increment('views_count', $count)
            );

            $flushed++;
        });

        $this->info("Buffer drenado: {$flushed} post(s) atualizados.");

        return Command::SUCCESS;
    }
}
