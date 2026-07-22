<?php

use Illuminate\Support\Facades\Storage;

/**
 * Gera a URL pública de um asset de mídia (imagem, vídeo etc.).
 * Transparente para disco local ('public') e Cloudflare R2 — controlado por IMAGE_DISK no .env.
 */
function image_url(?string $path): string
{
    if (empty($path)) {
        return '';
    }

    return Storage::disk(config('filesystems.image_disk', 'public'))->url($path);
}

/**
 * Formata um tamanho em bytes para uma unidade legível (B, KB, MB, GB).
 */
function human_filesize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
    $i = min($i, count($units) - 1);

    return round($bytes / (1024 ** $i), $i ? 1 : 0).' '.$units[$i];
}
