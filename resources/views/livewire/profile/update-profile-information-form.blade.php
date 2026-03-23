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
    public string $blog_description = '';
    public string $about_me = '';
    public $photo;
    public $favicon;

    // Campos Gemini IA
    public string $gemini_api_key = '';
    public string $gemini_model = 'gemini-2.0-flash';
    public string $gemini_ai_name = '';
    public string $gemini_persona = '';
    public string $gemini_accent_color = '#7c3aed';
    public $gemini_ai_photo;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->blog_description = Auth::user()->blog_description ?? '';
        $this->about_me = Auth::user()->about_me ?? '';
        $this->gemini_api_key = Auth::user()->gemini_api_key ?? '';
        $this->gemini_model = Auth::user()->gemini_model ?? 'gemini-2.0-flash';
        $this->gemini_ai_name = Auth::user()->gemini_ai_name ?? '';
        $this->gemini_persona = Auth::user()->gemini_persona ?? '';
        $this->gemini_accent_color = Auth::user()->gemini_accent_color ?? '#7c3aed';
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
            'photo' => ['nullable', 'image', 'max:10240'],
            'blog_description' => ['nullable', 'string', 'max:255'],
            'about_me' => ['nullable', 'string', 'max:5000'],
            'favicon' => ['nullable', 'image', 'mimes:ico,png,jpg,jpeg', 'max:10240'],
            'gemini_api_key' => ['nullable', 'string', 'max:255'],
            'gemini_model' => ['nullable', 'string', 'max:100'],
            'gemini_ai_name' => ['nullable', 'string', 'max:100'],
            'gemini_persona' => ['nullable', 'string', 'max:5000'],
            'gemini_accent_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'gemini_ai_photo' => ['nullable', 'image', 'max:10240'],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'blog_description' => $validated['blog_description'],
            'about_me' => $validated['about_me'],
            'gemini_api_key' => $validated['gemini_api_key'],
            'gemini_model' => $validated['gemini_model'] ?? 'gemini-2.0-flash',
            'gemini_ai_name' => $validated['gemini_ai_name'],
            'gemini_persona' => $validated['gemini_persona'],
            'gemini_accent_color' => $validated['gemini_accent_color'] ?? '#7c3aed',
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

        if ($this->gemini_ai_photo) {
            if ($user->gemini_ai_photo) {
                Storage::disk('public')->delete($user->gemini_ai_photo);
            }
            $user->gemini_ai_photo = $this->gemini_ai_photo->store('ai-avatars', 'public');
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

        <!-- Descrição do Blog (SEO) -->
        <div>
            <x-input-label for="blog_description" :value="__('Descrição do Blog (SEO)')" />
            <x-text-input wire:model="blog_description" id="blog_description" type="text" class="mt-1 block w-full" maxlength="255" placeholder="Ex: Tecnologia, desenvolvimento e reflexões — por Demóstenes Albert." />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Máx. 255 caracteres. Usada como meta description em todo o blog.</p>
            <x-input-error class="mt-2" :messages="$errors->get('blog_description')" />
        </div>

        <!-- Sobre Mim (Bio) -->
        <div>
            <x-input-label for="about_me" :value="__('Sobre Mim')" />
            <textarea wire:model="about_me" id="about_me" name="about_me" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" rows="5" placeholder="Escreva um pouco sobre você..."></textarea>
            <x-input-error class="mt-2" :messages="$errors->get('about_me')" />
        </div>

        <!-- Separador -->
        <hr class="border-gray-200 dark:border-gray-700">

        <!-- Configurações da IA Comentarista -->
        <div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                <span>🤖</span> IA Comentarista (Gemini)
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Configure o bot que comenta seus posts de forma sarcástica.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Nome da IA -->
            <div>
                <x-input-label for="gemini_ai_name" :value="__('Nome da IA')" />
                <x-text-input wire:model="gemini_ai_name" id="gemini_ai_name" type="text" class="mt-1 block w-full" placeholder="Ex: BOT Sarcástico" />
                <x-input-error class="mt-2" :messages="$errors->get('gemini_ai_name')" />
            </div>

            <!-- Cor de destaque -->
            <div>
                <x-input-label for="gemini_accent_color" :value="__('Cor do bloco de comentário')" />
                <div class="flex items-center gap-3 mt-1">
                    <input type="color" wire:model="gemini_accent_color" id="gemini_accent_color"
                        class="h-10 w-14 rounded-md border border-gray-300 dark:border-gray-700 cursor-pointer p-0.5 bg-white dark:bg-gray-900" />
                    <span class="text-sm text-gray-500 dark:text-gray-400 font-mono" x-text="$wire.gemini_accent_color"></span>
                </div>
                <x-input-error class="mt-2" :messages="$errors->get('gemini_accent_color')" />
            </div>
        </div>

            <!-- Modelo -->
            <div>
                <x-input-label for="gemini_model" :value="__('Modelo Gemini')" />
                <select wire:model="gemini_model" id="gemini_model" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                    <option value="gemini-2.5-pro">gemini-2.5-pro (mais inteligente)</option>
                    <option value="gemini-2.0-flash">gemini-2.0-flash (rápido)</option>
                    <option value="gemini-2.0-flash-lite">gemini-2.0-flash-lite (econômico)</option>
                    <option value="gemini-1.5-pro">gemini-1.5-pro (mais capaz)</option>
                    <option value="gemini-1.5-flash">gemini-1.5-flash</option>
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('gemini_model')" />
            </div>

        <!-- API Key -->
        <div>
            <x-input-label for="gemini_api_key" :value="__('Chave de API do Gemini')" />
            <x-text-input wire:model="gemini_api_key" id="gemini_api_key" type="password" class="mt-1 block w-full font-mono" placeholder="AIza..." autocomplete="off" />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Obtenha sua chave em <a href="https://aistudio.google.com/app/apikey" target="_blank" class="underline">aistudio.google.com</a>.</p>
            <x-input-error class="mt-2" :messages="$errors->get('gemini_api_key')" />
        </div>

        <!-- Foto da IA -->
        <div>
            <x-input-label for="gemini_ai_photo" :value="__('Avatar da IA')" />
            <div class="flex items-center gap-4 mt-2">
                <div class="shrink-0">
                    @if ($gemini_ai_photo)
                        <img class="h-12 w-12 object-cover rounded-full" src="{{ $gemini_ai_photo->temporaryUrl() }}" alt="Avatar da IA">
                    @elseif (Auth::user()->gemini_ai_photo)
                        <img class="h-12 w-12 object-cover rounded-full" src="{{ asset('storage/' . Auth::user()->gemini_ai_photo) }}" alt="{{ Auth::user()->gemini_ai_name }}">
                    @else
                        <div class="h-12 w-12 rounded-full bg-purple-100 dark:bg-purple-900 flex items-center justify-center text-2xl">🤖</div>
                    @endif
                </div>
                <input type="file" wire:model="gemini_ai_photo" accept="image/*" class="block w-full text-xs text-gray-500" />
            </div>
            <x-input-error class="mt-2" :messages="$errors->get('gemini_ai_photo')" />
        </div>

        <!-- Persona -->
        <div>
            <x-input-label for="gemini_persona" :value="__('Persona da IA')" />
            <textarea wire:model="gemini_persona" id="gemini_persona" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" rows="5" placeholder="Ex: Você é um crítico literário sarcástico. Comente o artigo com ironia e humor, mas sem ser ofensivo...">{{ $gemini_persona }}</textarea>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Descreva como a IA deve se comportar ao comentar seus posts.</p>
            <x-input-error class="mt-2" :messages="$errors->get('gemini_persona')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            <x-action-message class="me-3" on="profile-updated">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>