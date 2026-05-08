<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\SitemapController;

// Rotas públicas — cacheadas via middleware global web
Volt::route('/', 'home')->name('home');
Volt::route('autor/{user}', 'about')->name('author.show');
Volt::route('categoria/{category:slug}', 'categories.show')->name('categories.show');
Volt::route('tag/{tag:slug}', 'tags.show')->name('tags.show');
Route::get('/feed.rss', FeedController::class)->name('feed');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemap-pages.xml', [SitemapController::class, 'pages'])->name('sitemap.pages');
Route::get('/sitemap-posts.xml', [SitemapController::class, 'posts'])->name('sitemap.posts');

// Post individual: conta view antes do cache via middleware
Volt::route('blog/{post:slug}', 'posts.show')->name('posts.show');

// Rotas autenticadas — nunca cacheadas
Route::middleware(['auth', 'verified', 'doNotCacheResponse'])->group(function () {
    Volt::route('dashboard', 'dashboard')->name('dashboard');
    Volt::route('posts', 'posts.index')->name('posts.index');
    Volt::route('posts/create', 'posts.create')->name('posts.create');
    Volt::route('posts/{post}/edit', 'posts.edit')->name('posts.edit');
    Volt::route('categorias', 'categories.index')->name('categories.index');
    Volt::route('settings/ai', 'settings.ai-providers')->name('settings.ai');
});

Route::view('profile', 'profile')
    ->middleware(['auth', 'doNotCacheResponse'])
    ->name('profile');

require __DIR__.'/auth.php';
