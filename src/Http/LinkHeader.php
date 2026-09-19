<?php

declare(strict_types=1);

namespace Vellum\Http;

use Symfony\Component\HttpFoundation\Response;

/**
 * Adds a value to a response's Link header without replacing what is there.
 *
 * Each value goes out as its own Link line, which HTTP treats the same as one
 * comma-separated line, and it saves quoting URLs that contain commas.
 */
final class LinkHeader
{
    public static function add(Response $response, string $url, string $rel, ?string $type = null): void
    {
        $value = '<'.$url.'>; rel="'.$rel.'"';

        if ($type !== null) {
            $value .= '; type="'.$type.'"';
        }

        $response->headers->set('Link', $value, false);
    }
}
