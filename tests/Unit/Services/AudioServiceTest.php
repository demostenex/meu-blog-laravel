<?php

namespace Tests\Unit\Services;

use App\Services\AudioService;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AudioServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.image_disk' => 'public']);
        Storage::fake('public');
    }

    #[Test]
    public function store_pcm_as_wav_saves_playable_wav_file_in_correct_directory(): void
    {
        $service = app(AudioService::class);
        $pcm = str_repeat("\x00\x01", 100);
        $base64 = base64_encode($pcm);

        $path = $service->storePcmAsWav($base64, 'post-audio');

        $this->assertStringStartsWith('post-audio/', $path);
        $this->assertStringEndsWith('.wav', $path);
        Storage::disk('public')->assertExists($path);
    }

    #[Test]
    public function store_pcm_as_wav_writes_a_valid_riff_wave_header_of_expected_size(): void
    {
        $service = app(AudioService::class);
        $pcm = str_repeat("\x00\x01", 100);
        $base64 = base64_encode($pcm);

        $path = $service->storePcmAsWav($base64, 'post-audio');
        $contents = Storage::disk('public')->get($path);

        $this->assertSame('RIFF', substr($contents, 0, 4));
        $this->assertSame('WAVE', substr($contents, 8, 4));
        $this->assertSame(44 + strlen($pcm), strlen($contents));
    }

    #[Test]
    public function store_pcm_as_wav_returns_unique_paths_for_different_calls(): void
    {
        $service = app(AudioService::class);
        $base64 = base64_encode(str_repeat("\x00\x01", 100));

        $path1 = $service->storePcmAsWav($base64, 'post-audio');
        $path2 = $service->storePcmAsWav($base64, 'post-audio');

        $this->assertNotEquals($path1, $path2);
    }

    #[Test]
    public function store_concatenated_pcm_as_wav_combines_chunks_in_order_into_one_file(): void
    {
        $service = app(AudioService::class);
        $chunk1 = str_repeat("\x00\x01", 50);
        $chunk2 = str_repeat("\x02\x03", 70);

        $path = $service->storeConcatenatedPcmAsWav([base64_encode($chunk1), base64_encode($chunk2)], 'post-audio');
        $contents = Storage::disk('public')->get($path);

        $this->assertSame('RIFF', substr($contents, 0, 4));
        $this->assertSame('WAVE', substr($contents, 8, 4));
        $this->assertSame(44 + strlen($chunk1) + strlen($chunk2), strlen($contents));
        $this->assertSame($chunk1.$chunk2, substr($contents, 44));
    }

    #[Test]
    public function store_concatenated_pcm_as_wav_works_with_a_single_chunk(): void
    {
        $service = app(AudioService::class);
        $chunk = str_repeat("\x00\x01", 100);

        $path = $service->storeConcatenatedPcmAsWav([base64_encode($chunk)], 'post-audio');
        $contents = Storage::disk('public')->get($path);

        $this->assertSame(44 + strlen($chunk), strlen($contents));
    }
}
