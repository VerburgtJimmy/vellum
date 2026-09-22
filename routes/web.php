<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vellum\Http\Controllers\AnswersController;
use Vellum\Http\Controllers\ChangelogController;
use Vellum\Http\Controllers\ContentFileController;
use Vellum\Http\Controllers\DocsController;
use Vellum\Http\Controllers\LlmsTxtController;
use Vellum\Http\Controllers\RawMarkdownController;
use Vellum\Http\Controllers\SearchIndexController;
use Vellum\Http\Controllers\SitemapController;

Route::get('/sitemap.xml', SitemapController::class)->name('vellum.sitemap');
Route::get('/llms.txt', [LlmsTxtController::class, 'index'])->name('vellum.llms');
Route::get('/llms-full.txt', [LlmsTxtController::class, 'full'])->name('vellum.llms.full');

Route::get('/_vellum/search.json', SearchIndexController::class)->name('vellum.search');
Route::get('/_vellum/answers.json', [AnswersController::class, 'index'])->name('vellum.answers');
Route::get('/_vellum/semantic.bin', [AnswersController::class, 'semantic'])->name('vellum.answers.semantic');
Route::get('/_vellum/answer', [AnswersController::class, 'answer'])->name('vellum.answers.answer');

Route::get('/_vellum/files/{path}', ContentFileController::class)
    ->where('path', '.*')
    ->name('vellum.content');
Route::get('/_vellum/raw/{slug}.md', RawMarkdownController::class)
    ->where('slug', '.+')
    ->name('vellum.raw');

if (app()->environment('local')) {
    Route::view('/_vellum/demo', 'vellum::pages.ui-demo')->name('vellum.ui-demo');
}

Route::get('/changelog.atom', [ChangelogController::class, 'feed'])->name('vellum.changelog.atom');
Route::get('/changelog', [ChangelogController::class, 'page'])->name('vellum.changelog');

Route::get('/', [DocsController::class, 'index'])->name('vellum.docs.index');
Route::get('/{slug}', [DocsController::class, 'show'])
    ->where('slug', '.*')
    ->name('vellum.docs.show');
