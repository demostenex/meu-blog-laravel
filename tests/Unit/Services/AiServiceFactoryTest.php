<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\AiServiceFactory;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AiServiceFactoryTest extends TestCase
{
    use DatabaseTransactions;

    private function userWithProvider(string $provider = 'gemini', string $model = 'gemini-2.0-flash', bool $isDefault = true): User
    {
        $user = User::factory()->create();

        $p = $user->aiProviders()->create([
            'provider'   => $provider,
            'api_key'    => 'fake-key',
            'is_default' => $isDefault,
        ]);

        $p->models()->create([
            'model'      => $model,
            'is_default' => true,
        ]);

        return $user;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function resolves_gemini_service_for_gemini_provider(): void
    {
        $user    = $this->userWithProvider('gemini');
        $factory = new AiServiceFactory();

        $service = $factory->for($user);

        $this->assertInstanceOf(GeminiService::class, $service);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function throws_when_user_has_no_providers(): void
    {
        $user    = User::factory()->create();
        $factory = new AiServiceFactory();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('não tem nenhum AI provider configurado');

        $factory->for($user);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function prefers_default_provider_when_multiple_exist(): void
    {
        $user = User::factory()->create();

        // Two providers: gemini (default) and openai (not default)
        $gemini = $user->aiProviders()->create([
            'provider'   => 'gemini',
            'api_key'    => 'key-gemini',
            'is_default' => true,
        ]);
        $gemini->models()->create(['model' => 'gemini-2.0-flash', 'is_default' => true]);

        $openai = $user->aiProviders()->create([
            'provider'   => 'openai',
            'api_key'    => 'key-openai',
            'is_default' => false,
        ]);
        $openai->models()->create(['model' => 'gpt-4o', 'is_default' => true]);

        $factory = new AiServiceFactory();
        $service = $factory->for($user->fresh());

        // Should resolve the gemini (default) provider
        $this->assertInstanceOf(GeminiService::class, $service);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function uses_default_model_for_provider(): void
    {
        $user = User::factory()->create();
        $p    = $user->aiProviders()->create([
            'provider'   => 'gemini',
            'api_key'    => 'fake-key',
            'is_default' => true,
        ]);
        $p->models()->create(['model' => 'gemini-2.0-flash-lite', 'is_default' => false]);
        $p->models()->create(['model' => 'gemini-1.5-pro', 'is_default' => true]);

        $factory  = new AiServiceFactory();
        $provider = $user->aiProviders()->with('defaultModel')->first();

        $service = $factory->make($provider);

        $this->assertInstanceOf(GeminiService::class, $service);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function throws_on_unsupported_provider(): void
    {
        $user = User::factory()->create();
        $p    = $user->aiProviders()->create([
            'provider'   => 'unknown_provider',
            'api_key'    => 'fake-key',
            'is_default' => true,
        ]);
        $p->models()->create(['model' => 'some-model', 'is_default' => true]);

        $factory  = new AiServiceFactory();
        $provider = $user->aiProviders()->with('defaultModel')->first();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Provider não suportado');

        $factory->make($provider);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function falls_back_to_default_model_name_when_no_model_row_exists(): void
    {
        $user = User::factory()->create();
        $p    = $user->aiProviders()->create([
            'provider'   => 'gemini',
            'api_key'    => 'fake-key',
            'is_default' => true,
        ]);

        $factory  = new AiServiceFactory();
        $provider = $user->aiProviders()->with('defaultModel')->first();

        $service = $factory->make($provider);

        $this->assertInstanceOf(GeminiService::class, $service);
    }
}
