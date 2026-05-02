<?php

namespace Tests\Unit\Services;

use App\Services\ImageService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Força disco 'public' para os testes base, independente do IMAGE_DISK do .env
        config(['filesystems.image_disk' => 'public']);
        Storage::fake('public');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function store_from_base64_saves_webp_file_in_correct_directory(): void
    {
        $service = app(ImageService::class);
        $base64  = $this->createMinimalPngBase64();

        $path = $service->storeFromBase64($base64, 'covers');

        $this->assertStringStartsWith('covers/', $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function store_from_base64_returns_unique_paths_for_different_calls(): void
    {
        $service = app(ImageService::class);
        $base64  = $this->createMinimalPngBase64();

        $path1 = $service->storeFromBase64($base64, 'covers');
        $path2 = $service->storeFromBase64($base64, 'covers');

        $this->assertNotEquals($path1, $path2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function store_from_base64_stores_in_given_directory(): void
    {
        $service = app(ImageService::class);
        $base64  = $this->createMinimalPngBase64();

        $path = $service->storeFromBase64($base64, 'thumbnails');

        $this->assertStringStartsWith('thumbnails/', $path);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function store_from_base64_uses_r2_disk_when_configured(): void
    {
        Storage::fake('r2');
        config(['filesystems.image_disk' => 'r2']);

        $service = app(ImageService::class);
        $base64  = $this->createMinimalPngBase64();

        $path = $service->storeFromBase64($base64, 'covers');

        $this->assertStringStartsWith('covers/', $path);
        Storage::disk('r2')->assertExists($path);
        // Não deve ter ido para o disco público
        Storage::disk('public')->assertMissing($path);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function store_compressed_uses_r2_disk_when_configured(): void
    {
        Storage::fake('r2');
        config(['filesystems.image_disk' => 'r2']);

        $service = app(ImageService::class);
        $file    = $this->createUploadedImageFile();

        $path = $service->storeCompressed($file, 'profiles', 100, 100);

        $this->assertStringStartsWith('profiles/', $path);
        Storage::disk('r2')->assertExists($path);
    }

    protected function tearDown(): void
    {
        config(['filesystems.image_disk' => 'public']);
        parent::tearDown();
    }

    private function createUploadedImageFile(): \Illuminate\Http\UploadedFile
    {
        $tmpPath = sys_get_temp_dir() . '/test_image_' . uniqid() . '.png';

        $im  = imagecreatetruecolor(10, 10);
        $red = imagecolorallocate($im, 255, 0, 0);
        imagefill($im, 0, 0, $red);
        imagepng($im, $tmpPath);
        imagedestroy($im);

        return new \Illuminate\Http\UploadedFile($tmpPath, 'test.png', 'image/png', null, true);
    }

    /**
     * Cria um PNG 1×1 pixel vermelho como base64, suficiente para
     * o Intervention Image processar sem precisar de fixture de arquivo.
     */
    private function createMinimalPngBase64(): string
    {
        $im = imagecreatetruecolor(10, 10);
        $red = imagecolorallocate($im, 255, 0, 0);
        imagefill($im, 0, 0, $red);

        ob_start();
        imagepng($im);
        $data = ob_get_clean();
        imagedestroy($im);

        return base64_encode($data);
    }
}
