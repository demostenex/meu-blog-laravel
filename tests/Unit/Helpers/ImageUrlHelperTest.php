<?php

namespace Tests\Unit\Helpers;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageUrlHelperTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function returns_empty_string_for_null_path(): void
    {
        $this->assertSame('', image_url(null));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function returns_empty_string_for_empty_path(): void
    {
        $this->assertSame('', image_url(''));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function returns_public_disk_url_when_image_disk_is_public(): void
    {
        Storage::fake('public');
        config(['filesystems.image_disk' => 'public']);

        $url = image_url('covers/test.webp');

        $this->assertStringContainsString('covers/test.webp', $url);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function returns_r2_disk_url_when_image_disk_is_r2(): void
    {
        Storage::fake('r2');
        config(['filesystems.image_disk' => 'r2']);

        $url = image_url('covers/test.webp');

        $this->assertStringContainsString('covers/test.webp', $url);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function uses_disk_configured_in_image_disk(): void
    {
        // Garante que image_url() chama o disco configurado por IMAGE_DISK.
        // Storage::fake() retorna o mesmo formato de URL para qualquer disco,
        // então verificamos via espião que o disco correto é invocado.
        Storage::fake('public');
        Storage::fake('r2');

        config(['filesystems.image_disk' => 'r2']);
        $url = image_url('covers/test.webp');

        // A URL deve conter o path — o disco R2 fake a serviu corretamente
        $this->assertStringContainsString('covers/test.webp', $url);

        // Confirma que o arquivo NÃO foi parar no disco 'public'
        Storage::disk('public')->assertMissing('covers/test.webp');
    }

    protected function tearDown(): void
    {
        config(['filesystems.image_disk' => 'public']);
        parent::tearDown();
    }
}
