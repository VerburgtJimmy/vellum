<?php

declare(strict_types=1);

namespace Vellum\Changelog;

/**
 * One Keep a Changelog heading and its rendered body.
 */
final readonly class ChangelogRelease
{
    public function __construct(
        public string $version,
        public bool $unreleased,
        public ?string $date,
        public string $id,
        public string $html,
        public ?string $url = null,
    ) {}
}
