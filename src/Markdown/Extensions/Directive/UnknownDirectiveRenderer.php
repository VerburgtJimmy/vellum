<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Directive;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Vellum\Exceptions\UnknownDirectiveException;

/**
 * Last renderer to run for a DirectiveBlock. Every real directive renderer sits
 * above it and returns null only when the name is not one it handles, so
 * reaching here means nothing claimed the directive.
 *
 * Without this, CommonMark throws NoMatchingRendererException, which names an
 * internal class rather than the typo the author made.
 */
final class UnknownDirectiveRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?\Stringable
    {
        if (! $node instanceof DirectiveBlock) {
            return null;
        }

        throw UnknownDirectiveException::missing($node->getName());
    }
}
