<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Models\UserAiModel;
use App\Models\UserAiPersona;
use App\Models\UserAiProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiProvidersTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    // ── Acesso ──────────────────────────────────────────────────────────────

    #[Test]
    public function pagina_settings_ai_acessivel_para_usuario_autenticado(): void
    {
        $this->get(route('settings.ai'))->assertOk();
    }

    #[Test]
    public function usuario_nao_autenticado_e_redirecionado(): void
    {
        auth()->logout();
        $this->get(route('settings.ai'))->assertRedirect(route('login'));
    }

    // ── Providers ───────────────────────────────────────────────────────────

    #[Test]
    public function pode_adicionar_provider_gemini(): void
    {
        \Livewire\Volt\Volt::test('settings.ai-providers')
            ->set('provider', 'gemini')
            ->set('api_key', 'AIza-fake-key')
            ->set('is_default', true)
            ->call('saveProvider');

        $this->assertDatabaseHas('user_ai_providers', [
            'user_id'  => $this->user->id,
            'provider' => 'gemini',
        ]);
    }

    #[Test]
    public function nao_permite_dois_providers_do_mesmo_tipo(): void
    {
        $this->user->aiProviders()->create([
            'provider'   => 'gemini',
            'api_key'    => 'key1',
            'is_default' => true,
        ]);

        \Livewire\Volt\Volt::test('settings.ai-providers')
            ->set('provider', 'gemini')
            ->set('api_key', 'key2')
            ->call('saveProvider')
            ->assertHasErrors('provider');
    }

    #[Test]
    public function pode_editar_provider_existente(): void
    {
        $persona = $this->user->aiPersonas()->create([
            'name' => 'Kikito', 'accent_color' => '#7c3aed',
        ]);

        $provider = $this->user->aiProviders()->create([
            'provider'   => 'gemini',
            'api_key'    => 'old-key',
            'is_default' => true,
        ]);

        \Livewire\Volt\Volt::test('settings.ai-providers')
            ->call('openProviderForm', $provider->id)
            ->set('persona_id', $persona->id)
            ->call('saveProvider');

        $this->assertDatabaseHas('user_ai_providers', [
            'id'         => $provider->id,
            'persona_id' => $persona->id,
        ]);
    }

    #[Test]
    public function pode_excluir_provider(): void
    {
        $provider = $this->user->aiProviders()->create([
            'provider' => 'gemini', 'api_key' => 'key', 'is_default' => true,
        ]);

        \Livewire\Volt\Volt::test('settings.ai-providers')
            ->call('deleteProvider', $provider->id);

        $this->assertDatabaseMissing('user_ai_providers', ['id' => $provider->id]);
    }

    #[Test]
    public function pode_definir_provider_como_padrao_e_desmarca_outros(): void
    {
        $p1 = $this->user->aiProviders()->create(['provider' => 'gemini', 'api_key' => 'k1', 'is_default' => true]);
        $p2 = $this->user->aiProviders()->create(['provider' => 'openai', 'api_key' => 'k2', 'is_default' => false]);

        \Livewire\Volt\Volt::test('settings.ai-providers')
            ->call('setDefaultProvider', $p2->id);

        $this->assertFalse($p1->fresh()->is_default);
        $this->assertTrue($p2->fresh()->is_default);
    }

    // ── Modelos ─────────────────────────────────────────────────────────────

    #[Test]
    public function pode_adicionar_modelo_de_texto(): void
    {
        $provider = $this->user->aiProviders()->create([
            'provider' => 'gemini', 'api_key' => 'key', 'is_default' => true,
        ]);

        \Livewire\Volt\Volt::test('settings.ai-providers')
            ->set('newModel', 'gemini-2.0-flash')
            ->set('newCapability', 'text')
            ->call('addModel', $provider->id);

        $this->assertDatabaseHas('user_ai_models', [
            'user_ai_provider_id' => $provider->id,
            'model'               => 'gemini-2.0-flash',
            'capability'          => 'text',
            'is_default'          => true,
        ]);
    }

    #[Test]
    public function pode_adicionar_modelo_de_imagem(): void
    {
        $provider = $this->user->aiProviders()->create([
            'provider' => 'gemini', 'api_key' => 'key', 'is_default' => true,
        ]);

        \Livewire\Volt\Volt::test('settings.ai-providers')
            ->set('newModel', 'gemini-3.1-flash-image-preview')
            ->set('newCapability', 'image')
            ->call('addModel', $provider->id);

        $this->assertDatabaseHas('user_ai_models', [
            'user_ai_provider_id' => $provider->id,
            'model'               => 'gemini-3.1-flash-image-preview',
            'capability'          => 'image',
            'is_default'          => true,
        ]);
    }

    #[Test]
    public function primeiro_modelo_de_cada_capability_vira_padrao(): void
    {
        $provider = $this->user->aiProviders()->create([
            'provider' => 'gemini', 'api_key' => 'key', 'is_default' => true,
        ]);
        $provider->models()->create(['model' => 'gemini-2.0-flash', 'capability' => 'text', 'is_default' => true]);

        \Livewire\Volt\Volt::test('settings.ai-providers')
            ->set('newModel', 'gemini-3.1-flash-image-preview')
            ->set('newCapability', 'image')
            ->call('addModel', $provider->id);

        $this->assertTrue(
            UserAiModel::where('user_ai_provider_id', $provider->id)
                ->where('capability', 'image')
                ->where('is_default', true)
                ->exists()
        );
    }

    #[Test]
    public function pode_trocar_modelo_padrao(): void
    {
        $provider = $this->user->aiProviders()->create([
            'provider' => 'gemini', 'api_key' => 'key', 'is_default' => true,
        ]);
        $m1 = $provider->models()->create(['model' => 'gemini-2.0-flash', 'capability' => 'text', 'is_default' => true]);
        $m2 = $provider->models()->create(['model' => 'gemini-1.5-pro',   'capability' => 'text', 'is_default' => false]);

        \Livewire\Volt\Volt::test('settings.ai-providers')->call('setDefaultModel', $m2->id);

        $this->assertFalse($m1->fresh()->is_default);
        $this->assertTrue($m2->fresh()->is_default);
    }

    #[Test]
    public function pode_excluir_modelo(): void
    {
        $provider = $this->user->aiProviders()->create([
            'provider' => 'gemini', 'api_key' => 'key', 'is_default' => true,
        ]);
        $model = $provider->models()->create(['model' => 'gemini-2.0-flash', 'capability' => 'text', 'is_default' => true]);

        \Livewire\Volt\Volt::test('settings.ai-providers')->call('deleteModel', $model->id);

        $this->assertDatabaseMissing('user_ai_models', ['id' => $model->id]);
    }

    // ── Personas ────────────────────────────────────────────────────────────

    #[Test]
    public function pode_criar_persona(): void
    {
        \Livewire\Volt\Volt::test('settings.ai-providers')
            ->set('tab', 'personas')
            ->set('personaName', 'Kikito Sarcástico')
            ->set('personaAiName', 'Kikito')
            ->set('personaContent', 'Você é sarcástico.')
            ->set('accentColor', '#7c3aed')
            ->set('personaDefault', true)
            ->call('savePersona');

        $this->assertDatabaseHas('user_ai_personas', [
            'user_id'  => $this->user->id,
            'name'     => 'Kikito Sarcástico',
            'ai_name'  => 'Kikito',
            'content'  => 'Você é sarcástico.',
        ]);
    }

    #[Test]
    public function pode_editar_persona(): void
    {
        $persona = $this->user->aiPersonas()->create([
            'name' => 'Velho Nome', 'accent_color' => '#7c3aed',
        ]);

        \Livewire\Volt\Volt::test('settings.ai-providers')
            ->set('tab', 'personas')
            ->call('openPersonaForm', $persona->id)
            ->set('personaName', 'Novo Nome')
            ->call('savePersona');

        $this->assertDatabaseHas('user_ai_personas', [
            'id'   => $persona->id,
            'name' => 'Novo Nome',
        ]);
    }

    #[Test]
    public function pode_excluir_persona(): void
    {
        $persona = $this->user->aiPersonas()->create([
            'name' => 'Kikito', 'accent_color' => '#7c3aed',
        ]);

        \Livewire\Volt\Volt::test('settings.ai-providers')
            ->call('deletePersona', $persona->id);

        $this->assertDatabaseMissing('user_ai_personas', ['id' => $persona->id]);
    }

    #[Test]
    public function persona_pode_ser_atribuida_a_multiplos_providers(): void
    {
        $persona = $this->user->aiPersonas()->create([
            'name' => 'Kikito', 'accent_color' => '#7c3aed',
        ]);

        $p1 = $this->user->aiProviders()->create(['provider' => 'gemini', 'api_key' => 'k1', 'is_default' => true]);
        $p2 = $this->user->aiProviders()->create(['provider' => 'openai', 'api_key' => 'k2', 'is_default' => false]);

        $p1->update(['persona_id' => $persona->id]);
        $p2->update(['persona_id' => $persona->id]);

        $this->assertSame($persona->id, $p1->fresh()->persona_id);
        $this->assertSame($persona->id, $p2->fresh()->persona_id);
        $this->assertSame(2, $persona->providers()->count());
    }

    #[Test]
    public function definir_persona_padrao_desmarca_as_outras(): void
    {
        $p1 = $this->user->aiPersonas()->create(['name' => 'A', 'is_default' => true,  'accent_color' => '#111111']);
        $p2 = $this->user->aiPersonas()->create(['name' => 'B', 'is_default' => false, 'accent_color' => '#222222']);

        \Livewire\Volt\Volt::test('settings.ai-providers')
            ->set('tab', 'personas')
            ->call('openPersonaForm', $p2->id)
            ->set('personaDefault', true)
            ->call('savePersona');

        $this->assertFalse($p1->fresh()->is_default);
        $this->assertTrue($p2->fresh()->is_default);
    }
}
