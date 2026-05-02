<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SyncMediaToR2 extends Command
{
    protected $signature = 'media:sync-to-r2
                            {--dry-run : Lista os arquivos sem enviar}
                            {--force  : Sobrescreve arquivos já existentes no R2}';

    protected $description = 'Copia todos os assets do disco local (public) para o Cloudflare R2 sem regenerar nada';

    private const DIRS = ['covers', 'profiles', 'ai-avatars', 'post-images', 'post-videos'];

    public function handle(): int
    {
        if (config('filesystems.image_disk') !== 'r2') {
            $this->warn('IMAGE_DISK não está configurado como "r2" no .env.');
            $this->line('O comando ainda vai rodar, mas as novas imagens continuarão indo para o disco local.');
            $this->newLine();
        }

        $local = Storage::disk('public');
        $dry   = $this->option('dry-run');
        $force = $this->option('force');

        // Coleta todos os arquivos do disco local
        $files = collect();

        foreach (self::DIRS as $dir) {
            foreach ($local->files($dir) as $file) {
                $files->push($file);
            }
        }

        // Favicon na raiz
        if ($local->exists('favicon.png')) {
            $files->push('favicon.png');
        }

        $total = $files->count();

        if ($total === 0) {
            $this->info('Nenhum arquivo encontrado no disco local.');
            return self::SUCCESS;
        }

        $this->info("Encontrados {$total} arquivo(s) para sincronizar.");
        $this->newLine();

        if ($dry) {
            $this->warn('[DRY RUN] Nenhum arquivo será enviado.');
            $files->each(fn ($f) => $this->line("  → {$f}"));
            return self::SUCCESS;
        }

        // Valida credenciais R2 antes de tentar conectar
        if (blank(config('filesystems.disks.r2.bucket'))) {
            $this->error('R2_BUCKET não configurado no .env.');
            $this->line('Preencha as variáveis R2_* no .env e rode php artisan config:clear.');
            return self::FAILURE;
        }
        if (blank(config('filesystems.disks.r2.key'))) {
            $this->error('R2_ACCESS_KEY_ID não configurado no .env.');
            return self::FAILURE;
        }
        if (blank(config('filesystems.disks.r2.endpoint'))) {
            $this->error('R2_ENDPOINT não configurado no .env.');
            return self::FAILURE;
        }

        // Inicializa o R2 apenas quando as credenciais estão presentes
        $r2     = Storage::disk('r2');
        $sent    = 0;
        $skipped = 0;
        $errors  = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %message%');
        $bar->setMessage('Iniciando...');
        $bar->start();

        foreach ($files as $file) {
            $bar->setMessage($file);

            try {
                if (! $force && $r2->exists($file)) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                $stream = $local->readStream($file);
                $r2->writeStream($file, $stream, ['visibility' => 'public']);
                fclose($stream);
                $sent++;
            } catch (\Throwable $e) {
                $errors++;
                $this->newLine();
                $this->error("Erro em {$file}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->setMessage('Concluído!');
        $bar->finish();

        $this->newLine(2);
        $this->table(
            ['Enviados', 'Já existiam (pulados)', 'Erros', 'Total'],
            [[$sent, $skipped, $errors, $total]]
        );

        if ($errors > 0) {
            $this->warn("Atenção: {$errors} arquivo(s) falharam. Rode novamente para tentar de novo.");
            return self::FAILURE;
        }

        $this->info('Sincronização concluída com sucesso!');
        return self::SUCCESS;
    }
}
