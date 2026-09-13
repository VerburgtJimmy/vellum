<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vellum\Http\Controllers\ContentFileController;
use Vellum\Http\Controllers\DocsController;
use Vellum\Http\Controllers\RawMarkdownController;
use Vellum\Http\Controllers\SearchIndexController;

Route::get('/_vellum/search.json', SearchIndexController::class)->name('vellum.search');
Route::get('/_vellum/search-{hash}.json', SearchIndexController::class)
    ->where('hash', '[a-f0-9]+')
    ->name('vellum.search.hashed');
Route::get('/_vellum/files/{path}', ContentFileController::class)
    ->where('path', '.*')
    ->name('vellum.content');
Route::get('/_vellum/raw/{slug}.md', RawMarkdownController::class)
    ->where('slug', '.+')
    ->name('vellum.raw');

if (app()->environment('local')) {
    Route::view('/_vellum/demo', 'vellum::pages.ui-demo')->name('vellum.ui-demo');
}

Route::get('/', [DocsController::class, 'index'])->name('vellum.docs.index');
Route::get('/{slug}', [DocsController::class, 'show'])
    ->where('slug', '.*')
    ->name('vellum.docs.show');
