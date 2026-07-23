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
        return $this->storeConcatenatedPcmAsWav([$base64Pcm], $directory);
    }

    /**
     * Mesma ideia de storePcmAsWav(), mas para vários pedaços de PCM (um por trecho de
     * texto sintetizado separadamente) concatenados num único WAV — necessário porque
     * uma única chamada de TTS não cobre textos muito longos (ver TtsService::generateAudio).
     * Escreve tudo em streaming direto num arquivo temporário, sem nunca montar o áudio
     * inteiro numa string só em memória.
     *
     * @param  string[]  $base64PcmChunks
     */
    public function storeConcatenatedPcmAsWav(array $base64PcmChunks, string $directory): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'audio_');
        $handle = fopen($tmpFile, 'wb');

        // Reserva os 44 bytes do cabeçalho — só sabemos o tamanho total dos dados no final.
        fseek($handle, 44);

        $totalBytes = 0;
        foreach ($base64PcmChunks as $base64Pcm) {
            $pcm = base64_decode($base64Pcm);
            $totalBytes += strlen($pcm);
            fwrite($handle, $pcm);
            unset($pcm);
        }

        fseek($handle, 0);
        fwrite($handle, $this->wavHeader($totalBytes));
        fclose($handle);

        $path = $directory.'/'.Str::uuid().'.wav';

        try {
            $stream = fopen($tmpFile, 'rb');
            Storage::disk(config('filesystems.image_disk', 'public'))->put($path, $stream);
            fclose($stream);
        } finally {
            @unlink($tmpFile);
        }

        return $path;
    }

    private function wavHeader(int $dataSize, int $sampleRate = 24000, int $channels = 1, int $bitsPerSample = 16): string
    {
        $byteRate = $sampleRate * $channels * $bitsPerSample / 8;
        $blockAlign = $channels * $bitsPerSample / 8;

        return 'RIFF'.pack('V', 36 + $dataSize).'WAVE'
            .'fmt '.pack('V', 16).pack('v', 1).pack('v', $channels)
            .pack('V', $sampleRate).pack('V', $byteRate).pack('v', $blockAlign).pack('v', $bitsPerSample)
            .'data'.pack('V', $dataSize);
    }
}
