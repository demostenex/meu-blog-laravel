<?php

namespace Tests\Feature\Posts;

use App\Models\AiComment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PostLanguageToggleTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function toggle_button_not_shown_when_no_english_version(): void
    {
        $post = Post::factory()->create([
            'user_id'      => $this->user->id,
            'published_at' => now(),
            'content_en'   => null,
        ]);

        $this->get(route('posts.show', $post->slug))
            ->assertOk()
            ->assertDontSee('Português')
            ->assertDontSee('English');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function toggle_button_shown_when_english_version_exists(): void
    {
        $post = Post::factory()->create([
            'user_id'      => $this->user->id,
            'published_at' => now(),
            'title_en'     => 'My Post',
            'content_en'   => 'English content here.',
        ]);

        $this->get(route('posts.show', $post->slug))
            ->assertOk()
            ->assertSee('Português')
            ->assertSee('English');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function lang_defaults_to_pt(): void
    {
        $post = Post::factory()->create([
            'user_id'      => $this->user->id,
            'published_at' => now(),
            'content_en'   => 'English content.',
        ]);

        Volt::test('posts.show', ['post' => $post])
            ->assertSet('lang', 'pt');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function switch_lang_to_en_updates_state(): void
    {
        $post = Post::factory()->create([
            'user_id'      => $this->user->id,
            'published_at' => now(),
            'content_en'   => 'English content.',
        ]);

        Volt::test('posts.show', ['post' => $post])
            ->call('switchLang', 'en')
            ->assertSet('lang', 'en');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function switch_lang_back_to_pt_updates_state(): void
    {
        $post = Post::factory()->create([
            'user_id'      => $this->user->id,
            'published_at' => now(),
            'content_en'   => 'English content.',
        ]);

        Volt::test('posts.show', ['post' => $post])
            ->call('switchLang', 'en')
            ->assertSet('lang', 'en')
            ->call('switchLang', 'pt')
            ->assertSet('lang', 'pt');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function invalid_lang_value_falls_back_to_pt(): void
    {
        $post = Post::factory()->create([
            'user_id'      => $this->user->id,
            'published_at' => now(),
            'content_en'   => 'English content.',
        ]);

        Volt::test('posts.show', ['post' => $post])
            ->call('switchLang', 'fr')
            ->assertSet('lang', 'pt');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function title_shows_english_version_when_lang_is_en(): void
    {
        $post = Post::factory()->create([
            'user_id'      => $this->user->id,
            'published_at' => now(),
            'title'        => 'Meu Artigo',
            'title_en'     => 'My Article',
            'content_en'   => 'English content.',
        ]);

        Volt::test('posts.show', ['post' => $post])
            ->assertSee('Meu Artigo')
            ->call('switchLang', 'en')
            ->assertSee('My Article')
            ->assertDontSee('Meu Artigo');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function ai_comment_shows_english_version_when_lang_is_en(): void
    {
        $post = Post::factory()->create([
            'user_id'      => $this->user->id,
            'published_at' => now(),
            'content_en'   => 'English content.',
        ]);

        AiComment::create([
            'post_id'    => $post->id,
            'content'    => 'Comentário original em português.',
            'content_en' => 'Original comment in English.',
            'model'      => 'gemini-2.0-flash',
        ]);

        $this->get(route('posts.show', $post->slug))
            ->assertSee('Comentário original em português.');

        // Simula o toggle via Volt
        Volt::test('posts.show', ['post' => $post->fresh()])
            ->call('switchLang', 'en')
            ->assertSee('Original comment in English.')
            ->assertDontSee('Comentário original em português.');
    }
}
