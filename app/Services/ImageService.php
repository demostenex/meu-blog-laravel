<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageService
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Comprime e armazena um arquivo de imagem carregado.
     *
     * @param  UploadedFile  $file       Arquivo temporário do Livewire
     * @param  string        $directory  Subdiretório dentro do disco 'public'
     * @param  int           $maxWidth   Largura máxima em pixels
     * @param  int           $maxHeight  Altura máxima em pixels
     * @param  int           $quality    Qualidade de 1-100
     * @return string Caminho relativo salvo no disco 'public'
     */
    public function storeCompressed(
        UploadedFile $file,
        string $directory,
        int $maxWidth = 1920,
        int $maxHeight = 1920,
        int $quality = 82
    ): string {
        $image = $this->manager->read($file->getRealPath());
        $image->scaleDown($maxWidth, $maxHeight);

        $filename  = Str::uuid() . '.webp';
        $path      = $directory . '/' . $filename;
        $encoded   = $image->toWebp($quality);

        Storage::disk(config('filesystems.image_disk', 'public'))->put($path, (string) $encoded);

        return $path;
    }

    /**
     * Decodifica uma imagem em base64, comprime e armazena no disco 'public'.
     *
     * @param  string  $base64     Conteúdo da imagem em base64
     * @param  string  $directory  Subdiretório dentro do disco 'public'
     * @param  int     $maxWidth   Largura máxima em pixels
     * @param  int     $maxHeight  Altura máxima em pixels
     * @param  int     $quality    Qualidade de 1-100
     * @return string Caminho relativo salvo no disco 'public'
     */
    public function storeFromBase64(
        string $base64,
        string $directory,
        int $maxWidth = 1920,
        int $maxHeight = 1080,
        int $quality = 82
    ): string {
        $imageData = base64_decode($base64);

        $tmpPath = sys_get_temp_dir() . '/' . Str::uuid() . '.png';
        file_put_contents($tmpPath, $imageData);

        try {
            $image = $this->manager->read($tmpPath);
            $image->scaleDown($maxWidth, $maxHeight);

            $filename = Str::uuid() . '.webp';
            $path     = $directory . '/' . $filename;
            $encoded  = $image->toWebp($quality);

            Storage::disk(config('filesystems.image_disk', 'public'))->put($path, (string) $encoded);
        } finally {
            @unlink($tmpPath);
        }

        return $path;
    }

    /**
     * Comprime uma imagem já salva no disco 'public', mantendo o mesmo caminho.
     * Converte para WebP se a extensão original for jpg/jpeg/png.
     * Retorna o novo caminho (pode ter extensão diferente).
     */
    public function compressExisting(
        string $storagePath,
        int $maxWidth = 1920,
        int $maxHeight = 1920,
        int $quality = 82
    ): string {
        $fullPath = Storage::disk('public')->path($storagePath);

        if (! file_exists($fullPath)) {
            return $storagePath;
        }

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        // Mantém ICO e GIF sem processar
        if (in_array($ext, ['ico', 'gif', 'svg'])) {
            return $storagePath;
        }

        $image = $this->manager->read($fullPath);
        $image->scaleDown($maxWidth, $maxHeight);

        if (in_array($ext, ['jpg', 'jpeg'])) {
            $encoded  = $image->toJpeg($quality);
            $newPath  = preg_replace('/\.(jpg|jpeg)$/i', '.jpg', $storagePath);
        } else {
            $encoded  = $image->toWebp($quality);
            $newPath  = preg_replace('/\.[^.]+$/', '.webp', $storagePath);
        }

        Storage::disk('public')->put($newPath, (string) $encoded);

        if ($newPath !== $storagePath) {
            Storage::disk('public')->delete($storagePath);
        }

        return $newPath;
    }
}
