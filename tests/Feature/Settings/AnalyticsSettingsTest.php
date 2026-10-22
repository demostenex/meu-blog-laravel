<?php

namespace Tests\Feature\Settings;

use App\Http\Middleware\TrackPageView;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnalyticsSettingsTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function robots_uses_the_current_domain_instead_of_app_url(): void
    {
        $this->withoutMiddleware(TrackPageView::class);
        config(['app.url' => 'https://old.example']);
        foreach (['https://blog.example', 'https://another.example'] as $host) {
            $this->get($host.'/robots.txt')->assertOk()
                ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
                ->assertSee('Sitemap: '.$host.'/sitemap.xml', false)
                ->assertDontSee('old.example');
        }
    }

    #[Test]
    public function analytics_settings_require_authentication(): void
    {
        $this->get('/settings/analytics')->assertRedirect('/login');
    }

    #[Test]
    public function owner_can_save_and_disable_analytics_but_cannot_store_scripts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->get('/settings/analytics')->assertOk();
        Volt::test('settings.analytics')->set('measurementId', ' g-ABC123 ')
            ->call('save')->assertHasNoErrors();
        $this->assertSame('G-ABC123', $user->fresh()->google_analytics_id);
        Volt::test('settings.analytics')->set('measurementId', '<script>alert(1)</script>')
            ->call('save')->assertHasErrors('measurementId');
        $this->assertSame('G-ABC123', $user->fresh()->google_analytics_id);
        Volt::test('settings.analytics')->set('measurementId', '')->call('save')->assertHasNoErrors();
        $this->assertNull($user->fresh()->google_analytics_id);
    }

    #[Test]
    public function consent_controls_only_appear_when_a_tag_is_configured(): void
    {
        User::query()->update(['google_analytics_id' => null]);
        $user = User::first() ?? User::factory()->create();
        $this->actingAs($user);
        $this->get('/privacidade')->assertOk()->assertDontSee('googleAnalyticsConsent(');
        User::query()->update(['google_analytics_id' => 'G-ABC123']);
        $this->get('/privacidade')->assertOk()->assertSee('googleAnalyticsConsent(')
            ->assertSee('Rejeitar Google Analytics')->assertSee('Aceitar Google Analytics');
    }
}
