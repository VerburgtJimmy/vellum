<?php

declare(strict_types=1);

namespace Vellum\OpenApi\Samples;

/**
 * One language's way of writing a request down.
 *
 * Registered by key in vellum.openapi.samples. A project that wants Go or
 * Python implements this and names the class there.
 */
interface SampleGenerator
{
    /**
     * Tab label, e.g. "cURL".
     */
    public function label(): string;

    /**
     * Fence language for highlighting, e.g. "bash".
     */
    public function highlight(): string;

    public function generate(SampleRequest $request): string;
}
