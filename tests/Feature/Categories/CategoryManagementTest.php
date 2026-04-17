<?php

namespace Tests\Feature\Categories;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use DatabaseTransactions;

    #[\PHPUnit\Framework\Attributes\Test]
    public function guest_cannot_access_categories_admin(): void
    {
        $response = $this->get(route('categories.index'));

        $response->assertRedirect(route('login'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function authenticated_user_can_view_categories_page(): void
    {
        $user = User::factory()->create();

        Volt::actingAs($user)
            ->test('categories.index')
            ->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_create_category(): void
    {
        $user = User::factory()->create();

        Volt::actingAs($user)
            ->test('categories.index')
            ->set('name', 'Tecnologia Teste')
            ->set('description', 'Artigos sobre tecnologia')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'name' => 'Tecnologia Teste',
            'slug' => 'tecnologia-teste',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function category_name_is_required(): void
    {
        $user = User::factory()->create();

        Volt::actingAs($user)
            ->test('categories.index')
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_edit_existing_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => 'Original', 'slug' => 'original-test']);

        Volt::actingAs($user)
            ->test('categories.index')
            ->call('edit', $category->id)
            ->assertSet('editingId', $category->id)
            ->assertSet('name', 'Original')
            ->set('name', 'Atualizada')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Atualizada']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_cancel_edit(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $component = Volt::actingAs($user)
            ->test('categories.index')
            ->call('edit', $category->id)
            ->assertSet('editingId', $category->id);

        $component->call('cancelEdit')
            ->assertSet('editingId', null)
            ->assertSet('name', '');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_delete_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Volt::actingAs($user)
            ->test('categories.index')
            ->call('delete', $category->id);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function deleting_category_nullifies_posts_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id, 'category_id' => $category->id]);

        Volt::actingAs($user)
            ->test('categories.index')
            ->call('delete', $category->id);

        $this->assertNull($post->fresh()->category_id);
    }
}
