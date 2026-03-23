<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class OptimizeImages extends Command
{
    protected $signature = 'images:optimize
                            {--threshold=1024 : Tamanho mínimo em KB para otimizar (padrão: 1024 = 1 MB)}
                            {--dry-run : Mostra o que seria feito sem executar}';

    protected $description = 'Comprime imagens existentes no storage que ultrapassam o limite de tamanho';

    public function handle(ImageService $imageService): int
    {
        $thresholdKb    = (int) $this->option('threshold');
        $dryRun         = $this->option('dry-run');
        $thresholdBytes = $thresholdKb * 1024;

        $this->info("🔍 Buscando imagens acima de {$thresholdKb} KB...");
        if ($dryRun) {
            $this->warn('  [DRY-RUN] Nenhum arquivo será alterado.');
        }

        $directories = ['covers', 'post-images', 'profiles', 'ai-avatars'];
        $totalSaved  = 0;
        $totalFiles  = 0;

        foreach ($directories as $dir) {
            $files = Storage::disk('public')->files($dir);

            foreach ($files as $path) {
                $size = Storage::disk('public')->size($path);

                if ($size <= $thresholdBytes) {
                    continue;
                }

                $sizeBefore = round($size / 1024);
                $this->line("  <comment>{$path}</comment> ({$sizeBefore} KB)");

                if ($dryRun) {
                    $totalFiles++;
                    continue;
                }

                $newPath   = $imageService->compressExisting($path);
                $sizeAfter = round(Storage::disk('public')->size($newPath) / 1024);
                $saved     = $sizeBefore - $sizeAfter;
                $totalSaved += $saved;
                $totalFiles++;

                $this->line("    → <info>{$newPath}</info> ({$sizeAfter} KB, economizou {$saved} KB)");

                if ($newPath !== $path) {
                    $this->updateDatabaseReferences($path, $newPath);
                }
            }
        }

        $this->optimizeFavicon($imageService, $dryRun);

        $this->newLine();
        if ($dryRun) {
            $this->info("📋 {$totalFiles} arquivo(s) seriam otimizados.");
        } else {
            $this->info("✅ {$totalFiles} arquivo(s) otimizados. Total economizado: {$totalSaved} KB.");
        }

        return self::SUCCESS;
    }

    private function optimizeFavicon(ImageService $imageService, bool $dryRun): void
    {
        $faviconPath = 'favicon.png';

        if (! Storage::disk('public')->exists($faviconPath)) {
            return;
        }

        $sizeKb = round(Storage::disk('public')->size($faviconPath) / 1024);

        if ($sizeKb < 50) {
            return;
        }

        $this->line("  <comment>favicon.png</comment> ({$sizeKb} KB)");

        if ($dryRun) {
            return;
        }

        // Favicon mantido como PNG (formato esperado pelo navegador); salvo em-place
        $fullPath = Storage::disk('public')->path($faviconPath);
        $encoded  = (new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver()))
            ->read($fullPath)
            ->scaleDown(256, 256)
            ->toPng();
        Storage::disk('public')->put($faviconPath, (string) $encoded);

        $newSizeKb = round(Storage::disk('public')->size($faviconPath) / 1024);
        $this->line("    → <info>favicon.png</info> ({$newSizeKb} KB, economizou " . ($sizeKb - $newSizeKb) . " KB)");
    }

    private function updateDatabaseReferences(string $oldPath, string $newPath): void
    {
        $updated = Post::where('cover_image', $oldPath)->update(['cover_image' => $newPath]);
        if ($updated) {
            $this->line("    🗄  Atualizado {$updated} registro(s) em posts.cover_image");
        }

        $updated = User::where('profile_photo_path', $oldPath)->update(['profile_photo_path' => $newPath]);
        if ($updated) {
            $this->line("    🗄  Atualizado {$updated} registro(s) em users.profile_photo_path");
        }

        $updated = User::where('gemini_ai_photo', $oldPath)->update(['gemini_ai_photo' => $newPath]);
        if ($updated) {
            $this->line("    🗄  Atualizado {$updated} registro(s) em users.gemini_ai_photo");
        }
    }
}
