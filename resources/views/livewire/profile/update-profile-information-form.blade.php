<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $name = '';
    public string $email = '';
    public string $about_me = '';
    public $photo;
    public $favicon;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->about_me = Auth::user()->about_me ?? '';
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'photo' => ['nullable', 'image', 'max:10240'], // 10MB Max
            'about_me' => ['nullable', 'string', 'max:5000'],
            'favicon' => ['nullable', 'image', 'mimes:ico,png,jpg,jpeg', 'max:10240'], // 10MB Max para favicon
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'about_me' => $validated['about_me'],
        ]);

        if ($this->photo) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
            $user->profile_photo_path = $this->photo->store('profiles', 'public');
        }

        if ($this->favicon) {
            // Salva como favicon.png na pasta pública para facilitar
            $this->favicon->storeAs('', 'favicon.png', 'public');
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Informações do Perfil & Blog') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __("Atualize o seu nome, e-mail, foto e configurações do blog.") }}
        </p>
    </header>

    <form wire:submit="updateProfileInformation" class="mt-6 space-y-6">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Foto de Perfil -->
            <div>
                <x-input-label for="photo" :value="__('Foto de Perfil (Autor)')" />
                <div class="flex items-center gap-4 mt-2">
                    <div class="shrink-0">
                        @if ($photo)
                            <img class="h-12 w-12 object-cover rounded-full" src="{{ $photo->temporaryUrl() }}" alt="Nova foto de perfil">
                        @elseif (Auth::user()->profile_photo_path)
                            <img class="h-12 w-12 object-cover rounded-full" src="{{ asset('storage/' . Auth::user()->profile_photo_path) }}" alt="{{ Auth::user()->name }}">
                        @else
                            <img class="h-12 w-12 object-cover rounded-full" src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&color=7F9CF5&background=EBF4FF" alt="{{ Auth::user()->name }}">
                        @endif
                    </div>
                    <input type="file" wire:model="photo" accept="image/*" class="block w-full text-xs text-gray-500" />
                </div>
                <x-input-error class="mt-2" :messages="$errors->get('photo')" />
            </div>

            <!-- Favicon -->
            <div>
                <x-input-label for="favicon" :value="__('Favicon do Blog (Ícone da Aba)')" />
                <div class="flex items-center gap-4 mt-2">
                    <div class="shrink-0 bg-gray-100 dark:bg-gray-700 p-2 rounded-lg">
                        @if ($favicon)
                            <img class="h-8 w-8 object-contain" src="{{ $favicon->temporaryUrl() }}" alt="Novo Favicon">
                        @elseif (file_exists(public_path('storage/favicon.png')))
                            <img class="h-8 w-8 object-contain" src="{{ asset('storage/favicon.png') }}?v={{ time() }}" alt="Favicon Atual">
                        @else
                            <div class="h-8 w-8 flex items-center justify-center text-gray-400">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                        @endif
                    </div>
                    <input type="file" wire:model="favicon" accept="image/x-icon,image/png,image/jpeg" class="block w-full text-xs text-gray-500" />
                </div>
                <x-input-error class="mt-2" :messages="$errors->get('favicon')" />
            </div>
        </div>

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input wire:model="name" id="name" name="name" type="text" class="mt-1 block w-full" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" name="email" type="email" class="mt-1 block w-full" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800 dark:text-gray-200">
                        {{ __('Your email address is unverified.') }}

                        <button wire:click.prevent="sendVerification" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600 dark:text-green-400">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <!-- Sobre Mim (Bio) -->
        <div>
            <x-input-label for="about_me" :value="__('Sobre Mim')" />
            <textarea wire:model="about_me" id="about_me" name="about_me" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" rows="5" placeholder="Escreva um pouco sobre você..."></textarea>
            <x-input-error class="mt-2" :messages="$errors->get('about_me')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            <x-action-message class="me-3" on="profile-updated">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>