<?php

namespace App\Console\Commands;

use App\Mail\BackupReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class BackupRun extends Command
{
    protected $signature = 'backup:run
                            {--email= : E-mail destinatário (padrão: MAIL_FROM_ADDRESS)}
                            {--keep=5 : Quantos backups manter localmente na pasta storage/app/backups/}';

    protected $description = 'Gera backup do banco de dados e das imagens, e envia por e-mail';

    public function handle(): int
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $backupDir = storage_path('app/backups');

        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // 1. Dump do banco
        $this->info('📦 Gerando dump do banco de dados...');
        $dbDumpPath = $this->dumpDatabase($backupDir, $timestamp);

        if (! $dbDumpPath) {
            $this->error('❌ Falha ao gerar dump do banco. Verifique se o pg_dump está instalado.');
            return self::FAILURE;
        }

        $this->info('   ✅ ' . basename($dbDumpPath) . ' (' . round(filesize($dbDumpPath) / 1024) . ' KB)');

        // 2. Zip das imagens
        $this->info('🖼️  Comprimindo imagens...');
        $imagesZipPath = $this->zipImages($backupDir, $timestamp);

        if (! $imagesZipPath) {
            $this->error('❌ Falha ao compactar imagens.');
            return self::FAILURE;
        }

        $this->info('   ✅ ' . basename($imagesZipPath) . ' (' . round(filesize($imagesZipPath) / 1024) . ' KB)');

        // 3. URLs absolutas dos vídeos (muito pesados para anexar)
        $videoUrls = $this->collectVideoUrls();
        $this->info('🎬 ' . count($videoUrls) . ' vídeo(s) referenciados no e-mail com URL absoluta.');

        // 4. Envio do e-mail
        $to = $this->option('email') ?: config('mail.from.address');
        $this->info("📧 Enviando e-mail para {$to}...");

        Mail::to($to)->send(new BackupReport(
            dbDumpPath: $dbDumpPath,
            imagesZipPath: $imagesZipPath,
            videoUrls: $videoUrls,
            timestamp: $timestamp,
        ));

        $this->info('   ✅ E-mail enviado!');

        // 5. Limpeza de backups antigos
        $this->cleanupOldBackups($backupDir, (int) $this->option('keep'));

        $this->newLine();
        $this->info('✅ Backup concluído com sucesso!');

        return self::SUCCESS;
    }

    private function dumpDatabase(string $dir, string $timestamp): string|false
    {
        exec('which pg_dump 2>/dev/null', $_, $whichCode);
        if ($whichCode !== 0) {
            $this->error('❌ pg_dump não encontrado. Instale postgresql-client no container.');
            return false;
        }

        $config   = config('database.connections.pgsql');
        $path     = "{$dir}/db_{$timestamp}.sql.gz";
        $host     = $config['host'];
        $port     = (string) $config['port'];
        $database = $config['database'];
        $user     = $config['username'];
        $password = $config['password'];

        // Usa bash com pipefail para propagar falha do pg_dump (sh não suporta pipefail)
        $cmd = sprintf(
            'bash -c "set -o pipefail; PGPASSWORD=%s pg_dump -h %s -p %s -U %s %s | gzip > %s" 2>&1',
            escapeshellarg($password),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg($database),
            escapeshellarg($path)
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || ! file_exists($path) || filesize($path) < 100) {
            return false;
        }

        return $path;
    }

    private function zipImages(string $dir, string $timestamp): string|false
    {
        $zipPath     = "{$dir}/images_{$timestamp}.zip";
        $storagePath = Storage::disk('public')->path('');
        $directories = ['covers', 'post-images', 'profiles', 'ai-avatars'];
        $added       = 0;

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        foreach ($directories as $folder) {
            foreach (Storage::disk('public')->files($folder) as $file) {
                $fullPath = $storagePath . $file;
                if (file_exists($fullPath)) {
                    $zip->addFile($fullPath, $file);
                    $added++;
                }
            }
        }

        // Inclui o favicon se existir
        $faviconPath = $storagePath . 'favicon.png';
        if (file_exists($faviconPath)) {
            $zip->addFile($faviconPath, 'favicon.png');
            $added++;
        }

        $zip->close();

        $this->line("   📁 {$added} arquivo(s) incluído(s) no zip.");

        return file_exists($zipPath) ? $zipPath : false;
    }

    private function collectVideoUrls(): array
    {
        $files = Storage::disk('public')->files('post-videos');

        return array_map(
            fn ($file) => Storage::disk('public')->url($file),
            $files
        );
    }

    private function cleanupOldBackups(string $dir, int $keep): void
    {
        foreach (['db_*.sql.gz', 'images_*.zip'] as $pattern) {
            $files = glob("{$dir}/{$pattern}");

            if (! $files || count($files) <= $keep) {
                continue;
            }

            sort($files);
            $toDelete = array_slice($files, 0, count($files) - $keep);

            foreach ($toDelete as $file) {
                unlink($file);
            }

            $this->line('  🗑  ' . count($toDelete) . " backup(s) antigo(s) removido(s) ({$pattern})");
        }
    }
}
