<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SyncMediaToR2Test extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('r2');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function dry_run_lists_files_without_uploading(): void
    {
        Storage::disk('public')->put('covers/foto.webp', 'conteudo-fake');
        Storage::disk('public')->put('profiles/avatar.webp', 'conteudo-fake');

        $this->artisan('media:sync-to-r2', ['--dry-run' => true])
             ->assertSuccessful()
             ->expectsOutputToContain('covers/foto.webp')
             ->expectsOutputToContain('profiles/avatar.webp');

        // Nada deve ter ido para o R2
        Storage::disk('r2')->assertMissing('covers/foto.webp');
        Storage::disk('r2')->assertMissing('profiles/avatar.webp');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function syncs_covers_and_profiles_to_r2(): void
    {
        Storage::disk('public')->put('covers/capa.webp', 'bytes-da-capa');
        Storage::disk('public')->put('profiles/foto.webp', 'bytes-do-perfil');
        Storage::disk('public')->put('favicon.png', 'bytes-do-favicon');

        $this->artisan('media:sync-to-r2')
             ->assertSuccessful();

        Storage::disk('r2')->assertExists('covers/capa.webp');
        Storage::disk('r2')->assertExists('profiles/foto.webp');
        Storage::disk('r2')->assertExists('favicon.png');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function skips_files_already_existing_on_r2_without_force(): void
    {
        Storage::disk('public')->put('covers/capa.webp', 'versao-nova');
        Storage::disk('r2')->put('covers/capa.webp', 'versao-antiga');

        $this->artisan('media:sync-to-r2')
             ->assertSuccessful()
             ->expectsOutputToContain('1'); // 1 pulado

        // O conteúdo antigo no R2 deve ser preservado
        $this->assertEquals('versao-antiga', Storage::disk('r2')->get('covers/capa.webp'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function force_flag_overwrites_existing_files_on_r2(): void
    {
        Storage::disk('public')->put('covers/capa.webp', 'versao-nova');
        Storage::disk('r2')->put('covers/capa.webp', 'versao-antiga');

        $this->artisan('media:sync-to-r2', ['--force' => true])
             ->assertSuccessful();

        $this->assertEquals('versao-nova', Storage::disk('r2')->get('covers/capa.webp'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function reports_success_when_no_files_exist(): void
    {
        $this->artisan('media:sync-to-r2')
             ->assertSuccessful()
             ->expectsOutputToContain('Nenhum arquivo encontrado');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function syncs_all_supported_directories(): void
    {
        $dirs = ['covers', 'profiles', 'ai-avatars', 'post-images', 'post-videos'];

        foreach ($dirs as $dir) {
            Storage::disk('public')->put("{$dir}/arquivo.bin", 'conteudo');
        }

        $this->artisan('media:sync-to-r2')
             ->assertSuccessful();

        foreach ($dirs as $dir) {
            Storage::disk('r2')->assertExists("{$dir}/arquivo.bin");
        }
    }
}
