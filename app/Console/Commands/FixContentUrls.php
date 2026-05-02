<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;

class FixContentUrls extends Command
{
    protected $signature = 'media:fix-content-urls
                            {--old-url= : URL base a substituir (ex: URL anterior do R2). Se omitida, busca padrão /storage/}
                            {--dry-run  : Mostra as substituições sem salvar no banco}';

    protected $description = 'Substitui URLs de mídia no conteúdo dos posts usando image_url() como destino';

    private const DIRS = ['covers', 'profiles', 'ai-avatars', 'post-images', 'post-videos'];

    public function handle(): int
    {
        $dry    = $this->option('dry-run');
        $oldUrl = $this->option('old-url');

        if ($oldUrl) {
            // Troca uma URL base por outra — usa image_url() para gerar o destino correto
            $newUrl = rtrim(image_url('_'), '/_'); // extrai a base sem o path

            if (rtrim($oldUrl, '/') === $newUrl) {
                $this->warn('A URL antiga e a atual (via image_url) são iguais. Nada a fazer.');
                return self::SUCCESS;
            }

            $this->info("Substituindo: " . rtrim($oldUrl, '/'));
            $this->info("Por:          {$newUrl}  (via image_url)");
            $this->newLine();

            return $this->process(
                fn (string $text) => str_replace(rtrim($oldUrl, '/'), $newUrl, $text),
                $dry
            );
        }

        // Padrão: troca URLs /storage/ locais por image_url()
        $dirs    = implode('|', array_map('preg_quote', self::DIRS));
        $pattern = '#https?://[^/\s"\'<>]+/storage/((?:' . $dirs . ')/[^\s"\'<>&]+)#';

        return $this->process(
            fn (string $text) => preg_replace_callback(
                $pattern,
                fn ($m) => image_url($m[1]),
                $text
            ),
            $dry
        );
    }

    private function process(callable $transform, bool $dry): int
    {
        $posts      = Post::whereNotNull('content')->get(['id', 'title', 'content', 'content_en']);
        $totalPosts = 0;

        foreach ($posts as $post) {
            $changed = false;

            foreach (['content', 'content_en'] as $field) {
                if (empty($post->$field)) {
                    continue;
                }

                $new = $transform($post->$field);

                if ($new === $post->$field) {
                    continue;
                }

                $changed = true;

                if (! $dry) {
                    $post->$field = $new;
                }
            }

            if ($changed) {
                $totalPosts++;
                $this->line("Post #{$post->id}: <info>{$post->title}</info>");

                if (! $dry) {
                    $post->saveQuietly();
                }
            }
        }

        $this->newLine();

        if ($totalPosts === 0) {
            $this->info('Nenhuma URL para substituir encontrada.');
            return self::SUCCESS;
        }

        $dry
            ? $this->warn("[DRY RUN] {$totalPosts} post(s) seriam atualizados.")
            : $this->info("{$totalPosts} post(s) atualizados.");

        return self::SUCCESS;
    }
}
