<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\UserAiProvider;
use App\Models\UserAiModel;
use App\Models\UserAiPersona;
use App\Services\ImageService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use WithFileUploads;

    public string $tab = 'providers'; // 'providers' | 'personas'

    // ── Provider form ──────────────────────────────────────────────────────
    public string $provider     = '';
    public string $api_key      = '';
    public ?int   $persona_id   = null;
    public bool   $is_default   = false;
    public ?int   $editingProviderId = null;
    public bool   $showProviderForm  = false;

    // ── Model form ─────────────────────────────────────────────────────────
    public string $newModel      = '';
    public string $newCapability = 'text';

    // ── Persona form ───────────────────────────────────────────────────────
    public string $personaName    = '';
    public string $personaAiName  = '';
    public string $personaContent = '';
    public string $accentColor    = '#7c3aed';
    public $personaPhoto;
    public bool   $personaDefault = false;
    public ?int   $editingPersonaId = null;
    public bool   $showPersonaForm  = false;

    // ── Collections ────────────────────────────────────────────────────────
    public $providers;
    public $personas;

    public function mount(): void
    {
        $this->load();
    }

    private function load(): void
    {
        $this->providers = Auth::user()->aiProviders()->with(['persona', 'models'])->get();
        $this->personas  = Auth::user()->aiPersonas()->get();
    }

    // ── Providers ──────────────────────────────────────────────────────────

    public function openProviderForm(?int $id = null): void
    {
        $this->resetProviderForm();
        $this->showProviderForm = true;

        if ($id) {
            $p = UserAiProvider::findOrFail($id);
            $this->editingProviderId = $id;
            $this->provider          = $p->provider;
            $this->persona_id        = $p->persona_id;
            $this->is_default        = $p->is_default;
        }
    }

    public function saveProvider(): void
    {
        $validated = $this->validate([
            'provider'   => ['required', 'string', 'in:' . implode(',', array_keys(UserAiProvider::knownProviders()))],
            'api_key'    => $this->editingProviderId ? ['nullable', 'string'] : ['required', 'string'],
            'persona_id' => ['nullable', 'integer', 'exists:user_ai_personas,id'],
            'is_default' => ['boolean'],
        ]);

        $user = Auth::user();

        if ($this->editingProviderId) {
            $existing = UserAiProvider::findOrFail($this->editingProviderId);
            $data = [
                'persona_id' => $validated['persona_id'],
                'is_default' => $validated['is_default'],
            ];
            if (! empty($validated['api_key'])) {
                $data['api_key'] = $validated['api_key'];
            }
            $existing->update($data);
            $providerModel = $existing;
        } else {
            if ($user->aiProviders()->where('provider', $validated['provider'])->exists()) {
                $this->addError('provider', 'Você já tem este provider configurado.');
                return;
            }
            $providerModel = $user->aiProviders()->create([
                'provider'   => $validated['provider'],
                'api_key'    => $validated['api_key'],
                'persona_id' => $validated['persona_id'],
                'is_default' => $validated['is_default'],
            ]);
        }

        if ($validated['is_default']) {
            $user->aiProviders()->where('id', '!=', $providerModel->id)->update(['is_default' => false]);
        }

        $this->resetProviderForm();
        $this->load();
        session()->flash('status', 'provider-saved');
    }

    public function deleteProvider(int $id): void
    {
        UserAiProvider::findOrFail($id)->delete();
        $this->load();
    }

    public function setDefaultProvider(int $id): void
    {
        Auth::user()->aiProviders()->update(['is_default' => false]);
        Auth::user()->aiProviders()->where('id', $id)->update(['is_default' => true]);
        $this->load();
    }

    public function resetProviderForm(): void
    {
        $this->editingProviderId = null;
        $this->showProviderForm  = false;
        $this->provider = $this->api_key = '';
        $this->persona_id = null;
        $this->is_default = false;
        $this->resetErrorBag();
    }

    // ── Models ─────────────────────────────────────────────────────────────

    public function addModel(int $providerId): void
    {
        $this->validate([
            'newModel'      => ['required', 'string', 'max:100'],
            'newCapability' => ['required', 'in:text,image'],
        ]);

        $provider = UserAiProvider::findOrFail($providerId);
        $isFirst  = $provider->models()->where('capability', $this->newCapability)->doesntExist();

        $provider->models()->create([
            'model'      => $this->newModel,
            'capability' => $this->newCapability,
            'is_default' => $isFirst,
        ]);

        $this->newModel = '';
        $this->load();
    }

    public function setDefaultModel(int $modelId): void
    {
        $m = UserAiModel::findOrFail($modelId);
        $m->provider->models()->where('capability', $m->capability)->update(['is_default' => false]);
        $m->update(['is_default' => true]);
        $this->load();
    }

    public function deleteModel(int $modelId): void
    {
        UserAiModel::findOrFail($modelId)->delete();
        $this->load();
    }

    // ── Personas ───────────────────────────────────────────────────────────

    public function openPersonaForm(?int $id = null): void
    {
        $this->resetPersonaForm();
        $this->showPersonaForm = true;

        if ($id) {
            $p = UserAiPersona::findOrFail($id);
            $this->editingPersonaId = $id;
            $this->personaName      = $p->name;
            $this->personaAiName    = $p->ai_name ?? '';
            $this->personaContent   = $p->content ?? '';
            $this->accentColor      = $p->accent_color ?? '#7c3aed';
            $this->personaDefault   = $p->is_default;
        }
    }

    public function savePersona(): void
    {
        $validated = $this->validate([
            'personaName'    => ['required', 'string', 'max:100'],
            'personaAiName'  => ['nullable', 'string', 'max:100'],
            'personaContent' => ['nullable', 'string', 'max:5000'],
            'accentColor'    => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'personaPhoto'   => ['nullable', 'image', 'max:10240'],
            'personaDefault' => ['boolean'],
        ]);

        $user = Auth::user();
        $data = [
            'name'         => $validated['personaName'],
            'ai_name'      => $validated['personaAiName'],
            'content'      => $validated['personaContent'],
            'accent_color' => $validated['accentColor'] ?? '#7c3aed',
            'is_default'   => $validated['personaDefault'],
        ];

        if ($this->editingPersonaId) {
            $persona = UserAiPersona::findOrFail($this->editingPersonaId);
            $persona->update($data);
        } else {
            $persona = $user->aiPersonas()->create($data);
        }

        if ($this->personaPhoto) {
            if ($persona->ai_photo) {
                Storage::disk(config('filesystems.image_disk', 'public'))->delete($persona->ai_photo);
            }
            $persona->ai_photo = app(ImageService::class)->storeCompressed($this->personaPhoto, 'ai-avatars', 400, 400, 85);
            $persona->save();
        }

        if ($validated['personaDefault']) {
            $user->aiPersonas()->where('id', '!=', $persona->id)->update(['is_default' => false]);
        }

        $this->resetPersonaForm();
        $this->load();
        session()->flash('status', 'persona-saved');
    }

    public function deletePersona(int $id): void
    {
        $p = UserAiPersona::findOrFail($id);
        if ($p->ai_photo) {
            Storage::disk(config('filesystems.image_disk', 'public'))->delete($p->ai_photo);
        }
        $p->delete();
        $this->load();
    }

    public function resetPersonaForm(): void
    {
        $this->editingPersonaId = null;
        $this->showPersonaForm  = false;
        $this->personaName = $this->personaAiName = $this->personaContent = '';
        $this->accentColor    = '#7c3aed';
        $this->personaPhoto   = null;
        $this->personaDefault = false;
        $this->resetErrorBag();
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Configurações de IA
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('status') === 'provider-saved' || session('status') === 'persona-saved')
                <div class="rounded-md bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 px-4 py-3 text-sm text-green-800 dark:text-green-300">
                    {{ session('status') === 'persona-saved' ? 'Persona salva com sucesso.' : 'Provider salvo com sucesso.' }}
                </div>
            @endif

            {{-- ── Tabs ── --}}
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex gap-6">
                    <button wire:click="$set('tab', 'providers')" type="button"
                            class="pb-3 text-sm font-medium border-b-2 transition-colors
                                {{ $tab === 'providers'
                                    ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600' }}">
                        Providers & Modelos
                    </button>
                    <button wire:click="$set('tab', 'personas')" type="button"
                            class="pb-3 text-sm font-medium border-b-2 transition-colors
                                {{ $tab === 'personas'
                                    ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600' }}">
                        Personas
                        @if($personas->isNotEmpty())
                            <span class="ml-1.5 text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-1.5 py-0.5 rounded-full">
                                {{ $personas->count() }}
                            </span>
                        @endif
                    </button>
                </nav>
            </div>

            {{-- ════════════════════════════════════════════
                 TAB: PROVIDERS & MODELOS
                 ════════════════════════════════════════════ --}}
            @if($tab === 'providers')

                @forelse($providers as $p)
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">

                    {{-- Cabeçalho --}}
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center gap-4">
                        {{-- Avatar da persona vinculada --}}
                        @if($p->persona?->ai_photo)
                            <img src="{{ image_url($p->persona->ai_photo) }}"
                                 class="w-10 h-10 rounded-full object-cover shrink-0" alt="{{ $p->persona->ai_name }}">
                        @else
                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-bold shrink-0"
                                 style="background-color: {{ $p->persona?->accent_color ?? '#7c3aed' }}">
                                {{ strtoupper(substr($p->provider, 0, 2)) }}
                            </div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-gray-900 dark:text-gray-100 truncate">
                                {{ UserAiProvider::knownProviders()[$p->provider] ?? $p->provider }}
                                @if($p->persona)
                                    <span class="text-gray-400 dark:text-gray-500 font-normal text-sm">
                                        — {{ $p->persona->ai_name ?? $p->persona->name }}
                                    </span>
                                @endif
                            </p>
                            @if($p->is_default)
                                <span class="text-xs font-medium text-green-600 dark:text-green-400">● padrão</span>
                            @else
                                <button wire:click="setDefaultProvider({{ $p->id }})"
                                        class="text-xs text-gray-400 dark:text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 underline underline-offset-2">
                                    definir como padrão
                                </button>
                            @endif
                        </div>

                        <div class="flex items-center gap-3 shrink-0">
                            <button wire:click="openProviderForm({{ $p->id }})"
                                    class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300">
                                Editar
                            </button>
                            <button wire:click="deleteProvider({{ $p->id }})"
                                    wire:confirm="Excluir este provider e todos os seus modelos?"
                                    class="text-sm font-medium text-red-500 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300">
                                Excluir
                            </button>
                        </div>
                    </div>

                    {{-- Modelos --}}
                    <div class="px-6 py-4">
                        @foreach(['text' => 'Texto', 'image' => 'Imagem'] as $cap => $capLabel)
                            @php $capModels = $p->models->where('capability', $cap); @endphp
                            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2 mt-3 first:mt-0">
                                Modelos de {{ $capLabel }}
                            </p>

                            @if($capModels->isEmpty())
                                <p class="text-sm text-gray-500 dark:text-gray-400 italic mb-2">Nenhum modelo cadastrado.</p>
                            @else
                                <div class="space-y-1.5 mb-2">
                                    @foreach($capModels as $m)
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-mono text-gray-800 dark:text-gray-200">{{ $m->model }}</span>
                                            @if($m->is_default)
                                                <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 font-medium">padrão</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-3">
                                            @if(! $m->is_default)
                                                <button wire:click="setDefaultModel({{ $m->id }})"
                                                        class="text-xs text-gray-400 dark:text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 underline underline-offset-2">
                                                    usar como padrão
                                                </button>
                                            @endif
                                            <button wire:click="deleteModel({{ $m->id }})"
                                                    wire:confirm="Excluir este modelo?"
                                                    class="text-xs text-red-400 dark:text-red-500 hover:text-red-600 dark:hover:text-red-400">
                                                Remover
                                            </button>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            @endif
                        @endforeach

                        {{-- Adicionar modelo --}}
                        <div class="mt-4 flex gap-2 items-start">
                            <div class="w-28 shrink-0">
                                <select wire:model="newCapability"
                                        class="block w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <option value="text">Texto</option>
                                    <option value="image">Imagem</option>
                                </select>
                            </div>
                            <div class="flex-1">
                                <x-text-input wire:model="newModel" type="text" class="w-full text-sm"
                                              placeholder="{{ implode(', ', array_slice(UserAiProvider::knownModels($p->provider, $newCapability), 0, 1)) }}..." />
                                <x-input-error :messages="$errors->get('newModel')" class="mt-1" />
                            </div>
                            <button wire:click="addModel({{ $p->id }})" type="button"
                                    class="shrink-0 inline-flex items-center px-3 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white transition ease-in-out duration-150">
                                Adicionar
                            </button>
                        </div>

                        {{-- Sugestões --}}
                        @php $suggestions = UserAiProvider::knownModels($p->provider, $newCapability); @endphp
                        @if(count($suggestions) > 0)
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach($suggestions as $s)
                                    <button type="button" wire:click="$set('newModel', '{{ $s }}')"
                                            class="text-xs px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 font-mono transition-colors">
                                        {{ $s }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
                @empty
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-10 text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Nenhum AI provider configurado ainda.</p>
                    </div>
                @endforelse

                {{-- Botão / Formulário de Provider --}}
                @if(! $showProviderForm)
                    <div>
                        <button wire:click="openProviderForm()" type="button"
                                class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white transition ease-in-out duration-150">
                            + Adicionar provider
                        </button>
                    </div>
                @else
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ $editingProviderId ? 'Editar provider' : 'Novo provider' }}
                        </h3>
                    </div>
                    <div class="px-6 py-6 space-y-5">

                        @if(! $editingProviderId)
                        <div>
                            <x-input-label for="provider" value="Provider" />
                            <select wire:model="provider" id="provider"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                <option value="">Selecione...</option>
                                @foreach(UserAiProvider::knownProviders() as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('provider')" class="mt-2" />
                        </div>
                        @endif

                        <div>
                            <x-input-label for="api_key"
                                value="{{ $editingProviderId ? 'Chave de API (em branco = manter atual)' : 'Chave de API' }}" />
                            <x-text-input wire:model="api_key" id="api_key" type="password"
                                          class="mt-1 block w-full font-mono"
                                          placeholder="sk-... / AIza..." autocomplete="off" />
                            <x-input-error :messages="$errors->get('api_key')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="persona_id" value="Persona" />
                            <select wire:model="persona_id" id="persona_id"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                <option value="">Sem persona</option>
                                @foreach($personas as $persona)
                                    <option value="{{ $persona->id }}">
                                        {{ $persona->name }}{{ $persona->ai_name ? ' — ' . $persona->ai_name : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                A mesma persona pode ser usada em múltiplos providers.
                                <button type="button" wire:click="$set('tab', 'personas')"
                                        class="underline text-indigo-600 dark:text-indigo-400">
                                    Criar persona →
                                </button>
                            </p>
                            <x-input-error :messages="$errors->get('persona_id')" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-3">
                            <input type="checkbox" wire:model="is_default" id="is_default"
                                   class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-offset-gray-800" />
                            <x-input-label for="is_default" value="Definir como provider padrão" class="cursor-pointer !font-normal" />
                        </div>

                        <div class="flex items-center gap-3 pt-2">
                            <x-primary-button wire:click="saveProvider" type="button">Salvar</x-primary-button>
                            <x-secondary-button wire:click="resetProviderForm">Cancelar</x-secondary-button>
                        </div>
                    </div>
                </div>
                @endif

            @endif {{-- end tab providers --}}

            {{-- ════════════════════════════════════════════
                 TAB: PERSONAS
                 ════════════════════════════════════════════ --}}
            @if($tab === 'personas')

                @forelse($personas as $p)
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="px-6 py-4 flex items-center gap-4">
                        @if($p->ai_photo)
                            <img src="{{ image_url($p->ai_photo) }}"
                                 class="w-10 h-10 rounded-full object-cover shrink-0" alt="{{ $p->ai_name }}">
                        @else
                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-bold shrink-0"
                                 style="background-color: {{ $p->accent_color }}">
                                {{ strtoupper(substr($p->name, 0, 2)) }}
                            </div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $p->name }}</p>
                            @if($p->ai_name)
                                <p class="text-xs text-gray-500 dark:text-gray-400">Bot: {{ $p->ai_name }}</p>
                            @endif
                            @if($p->is_default)
                                <span class="text-xs font-medium text-green-600 dark:text-green-400">● padrão</span>
                            @endif
                            @if($p->content)
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5 truncate">
                                    {{ \Illuminate\Support\Str::limit($p->content, 80) }}
                                </p>
                            @endif
                        </div>

                        <div class="flex items-center gap-3 shrink-0">
                            <button wire:click="openPersonaForm({{ $p->id }})"
                                    class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300">
                                Editar
                            </button>
                            <button wire:click="deletePersona({{ $p->id }})"
                                    wire:confirm="Excluir esta persona?"
                                    class="text-sm font-medium text-red-500 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300">
                                Excluir
                            </button>
                        </div>
                    </div>
                </div>
                @empty
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-10 text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Nenhuma persona criada ainda.</p>
                    </div>
                @endforelse

                @if(! $showPersonaForm)
                    <div>
                        <button wire:click="openPersonaForm()" type="button"
                                class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white transition ease-in-out duration-150">
                            + Nova persona
                        </button>
                    </div>
                @else
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ $editingPersonaId ? 'Editar persona' : 'Nova persona' }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Uma persona pode ser atribuída a qualquer provider sem duplicação.
                        </p>
                    </div>
                    <div class="px-6 py-6 space-y-5">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <x-input-label for="personaName" value="Nome da persona" />
                                <x-text-input wire:model="personaName" id="personaName" type="text"
                                              class="mt-1 block w-full"
                                              placeholder="Ex: Kikito Sarcástico" />
                                <x-input-error :messages="$errors->get('personaName')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="personaAiName" value="Nome exibido no blog" />
                                <x-text-input wire:model="personaAiName" id="personaAiName" type="text"
                                              class="mt-1 block w-full"
                                              placeholder="Ex: Kikito" />
                                <x-input-error :messages="$errors->get('personaAiName')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="personaContent" value="Prompt de sistema (persona)" />
                            <textarea wire:model="personaContent" id="personaContent" rows="5"
                                      class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm text-sm"
                                      placeholder="Ex: Você é um crítico literário sarcástico. Comente o artigo com ironia e humor, mas sem ser ofensivo."></textarea>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Máx. 5000 caracteres.</p>
                            <x-input-error :messages="$errors->get('personaContent')" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <x-input-label for="accentColor" value="Cor de destaque" />
                                <div class="mt-1 flex items-center gap-3">
                                    <input type="color" wire:model="accentColor" id="accentColor"
                                           class="h-9 w-14 cursor-pointer rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 p-0.5" />
                                    <span class="text-sm text-gray-600 dark:text-gray-400 font-mono"
                                          x-text="$wire.accentColor"></span>
                                </div>
                                <x-input-error :messages="$errors->get('accentColor')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="personaPhoto" value="Avatar" />
                                <input type="file" wire:model="personaPhoto" id="personaPhoto"
                                       accept="image/*"
                                       class="mt-1 block w-full text-sm text-gray-500 dark:text-gray-400
                                              file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0
                                              file:text-xs file:font-semibold
                                              file:bg-gray-100 file:text-gray-700
                                              dark:file:bg-gray-700 dark:file:text-gray-300
                                              hover:file:bg-gray-200 dark:hover:file:bg-gray-600" />
                                <x-input-error :messages="$errors->get('personaPhoto')" class="mt-2" />
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <input type="checkbox" wire:model="personaDefault" id="personaDefault"
                                   class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-offset-gray-800" />
                            <x-input-label for="personaDefault" value="Usar como persona padrão" class="cursor-pointer !font-normal" />
                        </div>

                        <div class="flex items-center gap-3 pt-2">
                            <x-primary-button wire:click="savePersona" type="button">Salvar</x-primary-button>
                            <x-secondary-button wire:click="resetPersonaForm">Cancelar</x-secondary-button>
                        </div>
                    </div>
                </div>
                @endif

            @endif {{-- end tab personas --}}

        </div>
    </div>
</div>
