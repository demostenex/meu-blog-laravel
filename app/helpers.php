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
