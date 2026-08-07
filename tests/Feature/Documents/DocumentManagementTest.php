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
    public function authenticated_user_can_upload_document_and_attach_it_to_a_post_from_library(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        Volt::actingAs($user)
            ->test('documents.index')
            ->set('title', 'Material Vinculado')
            ->set('post_id', $post->id)
            ->set('file', UploadedFile::fake()->create('material.pdf', 500, 'application/pdf'))
            ->call('upload')
            ->assertHasNoErrors();

        $document = Document::firstWhere('title', 'Material Vinculado');

        $this->assertNotNull($document);
        $this->assertSame($post->id, $document->post_id);
        $this->assertDatabaseHas('document_post', [
            'document_id' => $document->id,
            'post_id' => $post->id,
        ]);
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
        $document = Document::factory()->create(['title' => 'Slides do Artigo']);

        $post->documents()->attach($document);

        $this->get(route('posts.show', $post->slug))
            ->assertOk()
            ->assertSee($document->title)
            ->assertSeeInOrder(['Arquivos do artigo', $document->title, 'id="article-content"'], false);
    }

    #[Test]
    public function post_show_page_only_displays_documents_from_that_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id, 'published_at' => now()]);
        $otherPost = Post::factory()->create(['user_id' => $user->id, 'published_at' => now()]);

        $document = Document::factory()->create(['title' => 'Material Deste Artigo']);
        $otherDocument = Document::factory()->create(['title' => 'Material de Outro Artigo']);

        $post->documents()->attach($document);
        $otherPost->documents()->attach($otherDocument);

        $this->get(route('posts.show', $post->slug))
            ->assertOk()
            ->assertSee($document->title)
            ->assertDontSee($otherDocument->title);
    }

    #[Test]
    public function post_editor_can_upload_single_document_attached_to_the_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        Volt::actingAs($user)
            ->test('posts.edit', ['post' => $post])
            ->set('documentTitle', 'Planilha do Artigo')
            ->set('documentFiles', [
                UploadedFile::fake()->create('planilha.xlsx', 300, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            ])
            ->call('uploadDocument')
            ->assertHasNoErrors();

        $document = Document::firstWhere('title', 'Planilha do Artigo');

        $this->assertNotNull($document);
        $this->assertSame($post->id, $document->post_id);
        $this->assertSame('planilha.xlsx', $document->original_filename);
        $this->assertDatabaseHas('document_post', [
            'document_id' => $document->id,
            'post_id' => $post->id,
        ]);
        Storage::disk('public')->assertExists($document->path);
    }

    #[Test]
    public function post_editor_can_upload_multiple_documents_attached_to_the_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        Volt::actingAs($user)
            ->test('posts.edit', ['post' => $post])
            ->set('documentFiles', [
                UploadedFile::fake()->create('manual.pdf', 200, 'application/pdf'),
                UploadedFile::fake()->create('slides.pptx', 350, 'application/vnd.openxmlformats-officedocument.presentationml.presentation'),
            ])
            ->call('uploadDocument')
            ->assertHasNoErrors();

        $documents = $post->fresh()->documents;

        $this->assertCount(2, $documents);
        $this->assertTrue($documents->pluck('title')->contains('manual'));
        $this->assertTrue($documents->pluck('title')->contains('slides'));
        Storage::disk('public')->assertExists($documents->firstWhere('original_filename', 'manual.pdf')->path);
        Storage::disk('public')->assertExists($documents->firstWhere('original_filename', 'slides.pptx')->path);
    }

    #[Test]
    public function post_editor_can_attach_existing_unlinked_document(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $document = Document::factory()->create(['post_id' => null]);

        Volt::actingAs($user)
            ->test('posts.edit', ['post' => $post])
            ->set('documentToAttach', $document->id)
            ->call('attachDocument')
            ->assertHasNoErrors();

        $this->assertSame($post->id, $document->fresh()->post_id);
        $this->assertDatabaseHas('document_post', [
            'document_id' => $document->id,
            'post_id' => $post->id,
        ]);
    }

    #[Test]
    public function post_editor_can_reuse_document_already_attached_to_another_post(): void
    {
        $user = User::factory()->create();
        $firstPost = Post::factory()->create(['user_id' => $user->id, 'published_at' => now()]);
        $secondPost = Post::factory()->create(['user_id' => $user->id, 'published_at' => now()]);
        $document = Document::factory()->create(['post_id' => $firstPost->id, 'title' => 'Arquivo Compartilhado']);

        $firstPost->documents()->attach($document);

        Volt::actingAs($user)
            ->test('posts.edit', ['post' => $secondPost])
            ->set('documentToAttach', $document->id)
            ->call('attachDocument')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('document_post', [
            'document_id' => $document->id,
            'post_id' => $firstPost->id,
        ]);
        $this->assertDatabaseHas('document_post', [
            'document_id' => $document->id,
            'post_id' => $secondPost->id,
        ]);

        $this->get(route('posts.show', $firstPost->slug))
            ->assertOk()
            ->assertSee($document->title);

        $this->get(route('posts.show', $secondPost->slug))
            ->assertOk()
            ->assertSee($document->title);
    }

    #[Test]
    public function post_editor_can_detach_document_without_deleting_file(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $path = UploadedFile::fake()->create('manual.pdf', 200, 'application/pdf')->store('documents', 'public');
        $document = Document::factory()->create(['post_id' => $post->id, 'path' => $path]);

        $post->documents()->attach($document);

        Volt::actingAs($user)
            ->test('posts.edit', ['post' => $post])
            ->call('detachDocument', $document->id);

        $this->assertNull($document->fresh()->post_id);
        $this->assertDatabaseMissing('document_post', [
            'document_id' => $document->id,
            'post_id' => $post->id,
        ]);
        Storage::disk('public')->assertExists($path);
    }
}
