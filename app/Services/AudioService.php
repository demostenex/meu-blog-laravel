<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AudioService
{
    /**
     * Decodifica áudio PCM cru em base64 (formato retornado pela API de TTS do Gemini:
     * 16-bit, mono, 24kHz, sem cabeçalho de arquivo), envelopa num WAV reproduzível
     * e salva no disco configurado em IMAGE_DISK.
     */
    public function storePcmAsWav(string $base64Pcm, string $directory): string
    {
        $pcm = base64_decode($base64Pcm);
        $wav = $this->wrapPcmAsWav($pcm);

        $path = $directory.'/'.Str::uuid().'.wav';

        Storage::disk(config('filesystems.image_disk', 'public'))->put($path, $wav);

        return $path;
    }

    private function wrapPcmAsWav(string $pcm, int $sampleRate = 24000, int $channels = 1, int $bitsPerSample = 16): string
    {
        $byteRate = $sampleRate * $channels * $bitsPerSample / 8;
        $blockAlign = $channels * $bitsPerSample / 8;
        $dataSize = strlen($pcm);

        $header = 'RIFF'.pack('V', 36 + $dataSize).'WAVE'
            .'fmt '.pack('V', 16).pack('v', 1).pack('v', $channels)
            .pack('V', $sampleRate).pack('V', $byteRate).pack('v', $blockAlign).pack('v', $bitsPerSample)
            .'data'.pack('V', $dataSize);

        return $header.$pcm;
    }
}
