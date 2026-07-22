<?php

namespace Tests\Feature\Documents;

use App\Models\Document;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.image_disk' => 'public']);
        Storage::fake('public');
    }

    #[Test]
    public function guest_cannot_access_documents_admin(): void
    {
        $response = $this->get(route('documents.index'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function authenticated_user_can_upload_document(): void
    {
        $user = User::factory()->create();

        Volt::actingAs($user)
            ->test('documents.index')
            ->set('title', 'Manual do Usuário')
            ->set('file', UploadedFile::fake()->create('manual.pdf', 500, 'application/pdf'))
            ->call('upload')
            ->assertHasNoErrors();

        $document = Document::firstWhere('title', 'Manual do Usuário');

        $this->assertNotNull($document);
        $this->assertSame('manual.pdf', $document->original_filename);
        Storage::disk('public')->assertExists($document->path);
    }

    #[Test]
    public function upload_rejects_disallowed_mime_type(): void
    {
        $user = User::factory()->create();

        Volt::actingAs($user)
            ->test('documents.index')
            ->set('title', 'Executável suspeito')
            ->set('file', UploadedFile::fake()->create('virus.exe', 100))
            ->call('upload')
            ->assertHasErrors(['file']);
    }

    #[Test]
    public function upload_rejects_file_over_10mb(): void
    {
        $user = User::factory()->create();

        Volt::actingAs($user)
            ->test('documents.index')
            ->set('title', 'Arquivo grande')
            ->set('file', UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf'))
            ->call('upload')
            ->assertHasErrors(['file']);
    }

    #[Test]
    public function deleting_document_removes_file_from_disk(): void
    {
        $user = User::factory()->create();
        $path = UploadedFile::fake()->create('manual.pdf', 200, 'application/pdf')->store('documents', 'public');
        $document = Document::factory()->create(['path' => $path]);

        Volt::actingAs($user)
            ->test('documents.index')
            ->call('delete', $document->id);

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        Storage::disk('public')->assertMissing($path);
    }

    #[Test]
    public function public_library_page_lists_documents(): void
    {
        $document = Document::factory()->create(['title' => 'Guia Completo']);

        $this->get(route('documents.library'))
            ->assertOk()
            ->assertSee($document->title);
    }

    #[Test]
    public function post_show_page_displays_attached_documents(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id, 'published_at' => now()]);
        $document = Document::factory()->create(['post_id' => $post->id, 'title' => 'Slides do Artigo']);

        $this->get(route('posts.show', $post->slug))
            ->assertOk()
            ->assertSee($document->title);
    }
}
