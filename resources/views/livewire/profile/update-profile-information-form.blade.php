<?php

use App\Models\User;
use App\Services\ImageService;
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
    public string $social_x = '';
    public string $social_instagram = '';
    public string $social_facebook = '';
    public string $social_linkedin = '';
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
        $this->social_x = Auth::user()->social_x ?? '';
        $this->social_instagram = Auth::user()->social_instagram ?? '';
        $this->social_facebook = Auth::user()->social_facebook ?? '';
        $this->social_linkedin = Auth::user()->social_linkedin ?? '';
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
            'social_x' => ['nullable', 'url', 'max:255'],
            'social_instagram' => ['nullable', 'url', 'max:255'],
            'social_facebook' => ['nullable', 'url', 'max:255'],
            'social_linkedin' => ['nullable', 'url', 'max:255'],
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
            'social_x' => $validated['social_x'],
            'social_instagram' => $validated['social_instagram'],
            'social_facebook' => $validated['social_facebook'],
            'social_linkedin' => $validated['social_linkedin'],
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
            $user->profile_photo_path = app(ImageService::class)->storeCompressed($this->photo, 'profiles', 400, 400, 85);
        }

        if ($this->favicon) {
            // Favicon salvo como PNG (formato esperado pelo navegador)
            $encoded = (new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver()))
                ->read($this->favicon->getRealPath())
                ->scaleDown(256, 256)
                ->toPng();
            Storage::disk('public')->put('favicon.png', (string) $encoded);
        }

        if ($this->gemini_ai_photo) {
            if ($user->gemini_ai_photo) {
                Storage::disk('public')->delete($user->gemini_ai_photo);
            }
            $user->gemini_ai_photo = app(ImageService::class)->storeCompressed($this->gemini_ai_photo, 'ai-avatars', 400, 400, 85);
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

        <!-- Redes Sociais -->
        <div class="space-y-3">
            <x-input-label :value="__('Redes Sociais')" />
            <p class="text-xs text-gray-500 dark:text-gray-400 -mt-1">Cole o link completo do seu perfil. Apenas ícones serão exibidos no blog.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-gray-400 shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.745l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    </span>
                    <x-text-input wire:model="social_x" id="social_x" type="url" class="block w-full text-sm" placeholder="https://x.com/seuperfil" />
                    <x-input-error :messages="$errors->get('social_x')" />
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-gray-400 shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    </span>
                    <x-text-input wire:model="social_instagram" id="social_instagram" type="url" class="block w-full text-sm" placeholder="https://instagram.com/seuperfil" />
                    <x-input-error :messages="$errors->get('social_instagram')" />
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-gray-400 shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </span>
                    <x-text-input wire:model="social_facebook" id="social_facebook" type="url" class="block w-full text-sm" placeholder="https://facebook.com/seuperfil" />
                    <x-input-error :messages="$errors->get('social_facebook')" />
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-gray-400 shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                    </span>
                    <x-text-input wire:model="social_linkedin" id="social_linkedin" type="url" class="block w-full text-sm" placeholder="https://linkedin.com/in/seuperfil" />
                    <x-input-error :messages="$errors->get('social_linkedin')" />
                </div>
            </div>
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