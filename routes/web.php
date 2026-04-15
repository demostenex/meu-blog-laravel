<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\SitemapController;

Volt::route('/', 'home')->name('home');
Volt::route('blog/{post:slug}', 'posts.show')->name('posts.show');
Volt::route('autor/{user}', 'about')->name('author.show');
Route::get('/feed.rss', FeedController::class)->name('feed');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemap-pages.xml', [SitemapController::class, 'pages'])->name('sitemap.pages');
Route::get('/sitemap-posts.xml', [SitemapController::class, 'posts'])->name('sitemap.posts');

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('dashboard', 'dashboard')->name('dashboard');
    Volt::route('posts', 'posts.index')->name('posts.index');
    Volt::route('posts/create', 'posts.create')->name('posts.create');
    Volt::route('posts/{post}/edit', 'posts.edit')->name('posts.edit');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
