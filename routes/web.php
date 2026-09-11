<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vellum\Http\Controllers\DocsController;
use Vellum\Http\Controllers\SearchIndexController;

Route::get('/_vellum/search.json', SearchIndexController::class)->name('vellum.search');
Route::get('/_vellum/search-{hash}.json', SearchIndexController::class)
    ->where('hash', '[a-f0-9]+')
    ->name('vellum.search.hashed');

Route::get('/', [DocsController::class, 'index'])->name('vellum.docs.index');
Route::get('/{slug}', [DocsController::class, 'show'])
    ->where('slug', '.*')
    ->name('vellum.docs.show');
