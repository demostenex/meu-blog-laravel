@php
    $measurementId = \App\Models\User::first()?->google_analytics_id;
    $measurementId = is_string($measurementId) && preg_match('/^G-[A-Z0-9]+$/', $measurementId) ? $measurementId : null;
@endphp

<section x-data="googleAnalyticsConsent(@js($measurementId))" @open-privacy-preferences.window="open = true"
    x-show="open" x-transition.opacity.duration.200ms style="display: none"
    aria-labelledby="privacy-toast-title" aria-describedby="privacy-toast-description"
    class="fixed bottom-4 right-4 left-4 sm:left-auto sm:w-96 z-[60] rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 shadow-2xl">
    <h2 id="privacy-toast-title" class="text-base font-semibold text-gray-900 dark:text-white">Sua privacidade</h2>
    <p id="privacy-toast-description" class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
        Usamos estatísticas próprias e Cloudflare para entender os acessos e manter o blog funcionando.
        @if ($measurementId)
            Você aceita também o Google Analytics para medir visitas e interações?
        @else
            No momento, não há Google Analytics ativado.
        @endif
        A escolha não desativa as estatísticas próprias do blog.
    </p>
    <div class="mt-4 grid grid-cols-2 gap-3">
        <button type="button" @click="choose(true)" class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-semibold text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-500">Aceitar</button>
        <button type="button" @click="choose(false)" class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-semibold text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-500">Recusar</button>
    </div>
    <a href="{{ route('privacy') }}" class="mt-4 inline-block text-sm text-blue-600 dark:text-blue-400 underline underline-offset-4">Ver como são usados</a>
</section>
