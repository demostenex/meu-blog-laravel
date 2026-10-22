<?php

use Livewire\Volt\Component;
use Spatie\ResponseCache\Facades\ResponseCache;

new class extends Component {
    public string $measurementId = '';

    public function mount(): void
    {
        $this->measurementId = auth()->user()->google_analytics_id ?? '';
    }

    public function save(): void
    {
        $this->measurementId = strtoupper(trim($this->measurementId));
        $this->validate(['measurementId' => ['nullable', 'regex:/^G-[A-Z0-9]+$/', 'max:64']]);
        auth()->user()->forceFill(['google_analytics_id' => $this->measurementId ?: null])->save();
        ResponseCache::clear();
        $this->dispatch('analytics-saved');
    }
}; ?>

<div class="max-w-3xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-bold mb-6">Google Analytics</h1>
    <form wire:submit="save" class="space-y-4">
        <p>Informe o ID de medição da sua tag do Google Analytics 4. A tag será instalada nas páginas públicas e carregada somente após o visitante aceitar o Google Analytics.</p>
        <x-input-label for="measurementId" value="ID de medição" />
        <x-text-input id="measurementId" wire:model="measurementId" placeholder="G-XXXXXXXXXX" class="w-full" />
        <x-input-error :messages="$errors->get('measurementId')" />
        <p class="text-sm">Deixe vazio para desativar. Não é necessário colar o código JavaScript da tag. Esta opção não controla tags instaladas fora do blog, como pelo Cloudflare.</p>
        <x-primary-button>Salvar</x-primary-button>
        <x-action-message on="analytics-saved">Configuração salva.</x-action-message>
    </form>
</div>
