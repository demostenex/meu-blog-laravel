<?php

use Livewire\Volt\Component;
use Illuminate\Support\Str;
use App\Models\Category;

new class extends Component {
    public string $name = '';
    public string $description = '';
    public ?int $editingId = null;

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
        ];
    }

    public function with(): array
    {
        return [
            'categories' => Category::withCount('posts')->orderBy('name')->get(),
        ];
    }

    public function save(): void
    {
        $this->validate();

        $slug = Str::slug($this->name);

        if ($this->editingId) {
            Category::findOrFail($this->editingId)->update([
                'name'        => $this->name,
                'slug'        => $slug,
                'description' => $this->description ?: null,
            ]);
            $this->reset('editingId');
        } else {
            Category::create([
                'name'        => $this->name,
                'slug'        => $slug,
                'description' => $this->description ?: null,
            ]);
        }

        $this->reset('name', 'description');
        session()->flash('status', 'Categoria salva!');
    }

    public function edit(int $id): void
    {
        $cat = Category::findOrFail($id);
        $this->editingId = $id;
        $this->name = $cat->name;
        $this->description = $cat->description ?? '';
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'name', 'description');
    }

    public function delete(int $id): void
    {
        Category::findOrFail($id)->delete();
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Categorias
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('status'))
                <div class="bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-300 text-sm px-4 py-3 rounded-lg">
                    {{ session('status') }}
                </div>
            @endif

            <!-- Formulário -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    {{ $editingId ? 'Editar Categoria' : 'Nova Categoria' }}
                </h3>
                <form wire:submit="save" class="space-y-4">
                    <div>
                        <x-input-label for="name" value="Nome" />
                        <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" placeholder="Ex: Tecnologia" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="description" value="Descrição (opcional)" />
                        <textarea wire:model="description" id="description" rows="2"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            placeholder="Breve descrição desta categoria"></textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-1" />
                    </div>
                    <div class="flex gap-3">
                        <x-primary-button type="submit">
                            {{ $editingId ? 'Salvar alterações' : 'Criar categoria' }}
                        </x-primary-button>
                        @if($editingId)
                            <x-secondary-button wire:click="cancelEdit" type="button">Cancelar</x-secondary-button>
                        @endif
                    </div>
                </form>
            </div>

            <!-- Lista -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($categories as $cat)
                    <div class="flex items-center justify-between px-6 py-4 gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('categories.show', $cat->slug) }}" target="_blank"
                                   class="font-semibold text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                    {{ $cat->name }}
                                </a>
                                <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 px-2 py-0.5 rounded-full">
                                    {{ $cat->posts_count }} {{ $cat->posts_count === 1 ? 'artigo' : 'artigos' }}
                                </span>
                            </div>
                            @if($cat->description)
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5 truncate">{{ $cat->description }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button wire:click="edit({{ $cat->id }})" type="button"
                                class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                                Editar
                            </button>
                            <button wire:click="delete({{ $cat->id }})"
                                wire:confirm="Remover '{{ $cat->name }}'? Os artigos ficam sem categoria."
                                type="button"
                                class="text-sm text-red-600 dark:text-red-400 hover:underline">
                                Excluir
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                        Nenhuma categoria criada ainda.
                    </div>
                @endforelse
            </div>

        </div>
    </div>
</div>
