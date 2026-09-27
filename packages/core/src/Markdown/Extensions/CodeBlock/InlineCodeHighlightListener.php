<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\CodeBlock;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Node\Inline\Text;

/**
 * Merges trailing {:lang} text into the preceding inline Code node for highlighting.
 */
final class InlineCodeHighlightListener
{
    public function __invoke(DocumentParsedEvent $event): void
    {
        $nodes = [];

        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof Code) {
                $nodes[] = $node;
            }
        }

        foreach ($nodes as $code) {
            $this->mergeLanguageSuffix($code);
        }
    }

    private function mergeLanguageSuffix(Code $code): void
    {
        $next = $code->next();

        if (! $next instanceof Text) {
            return;
        }

        if (preg_match('/^\{:([\w+-]+)\}/', $next->getLiteral(), $match) !== 1) {
            return;
        }

        $language = strtolower($match[1]);
        $remainder = substr($next->getLiteral(), strlen($match[0]));

        $code->data->set('vellum_language', $language);

        if ($remainder === '') {
            $next->detach();

            return;
        }

        $next->setLiteral($remainder);
    }
}
