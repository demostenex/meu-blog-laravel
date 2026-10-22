@php($measurementId = \App\Models\User::first()?->google_analytics_id)
@if (is_string($measurementId) && preg_match('/^G-[A-Z0-9]+$/', $measurementId))
    <section x-data="googleAnalyticsConsent(@js($measurementId))" class="max-w-5xl mx-auto px-4 py-6" aria-label="Preferências do Google Analytics">
        <div x-show="open" class="rounded-lg border border-gray-300 dark:border-gray-700 p-4 space-y-4">
            <p>Com sua autorização, usamos o Google Analytics para medir visitas, interações e origem dos acessos. Ele utiliza cookies e compartilha dados de navegação com o Google. Sua escolha vale apenas para o Google Analytics; as estatísticas próprias do blog continuam ativas. <a href="{{ route('privacy') }}" class="underline">Privacidade e cookies</a>.</p>
            <div class="flex flex-wrap gap-3">
                <button type="button" @click="choose(false)" class="border border-gray-400 rounded-md px-4 py-2">Rejeitar Google Analytics</button>
                <button type="button" @click="choose(true)" class="border border-gray-400 rounded-md px-4 py-2">Aceitar Google Analytics</button>
            </div>
        </div>
        <button type="button" @click="open = true" class="underline text-sm mt-3">Preferências do Google Analytics</button>
    </section>
@endif
