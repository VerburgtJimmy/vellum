<?php

declare(strict_types=1);

namespace Vellum\Exceptions;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Renders a content error as a Vellum-branded 500 instead of Laravel's generic
 * page. The reason and file are shown only when APP_DEBUG is on, so production
 * never leaks a filesystem path to a reader.
 *
 * Laravel calls render() on an exception that defines it. Tests using
 * withoutExceptionHandling() still see the exception itself.
 */
trait RendersDocsError
{
    public function render(Request $request): ?Response
    {
        if ($request->expectsJson()) {
            return null;
        }

        $debug = (bool) config('app.debug', false);

        $html = view()->file(
            dirname(__DIR__, 2).'/resources/views/pages/error.blade.php',
            [
                'detail' => $debug ? $this->getMessage() : null,
                'file' => $debug ? $this->docsErrorFile() : null,
            ],
        )->render();

        return new Response($html, 500, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * The docs file the error came from, when the exception knows it.
     * Not Exception::$file, which is the PHP source that threw.
     */
    protected function docsErrorFile(): ?string
    {
        return property_exists($this, 'docsFile') && is_string($this->docsFile)
            ? $this->docsFile
            : null;
    }
}
