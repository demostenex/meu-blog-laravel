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
