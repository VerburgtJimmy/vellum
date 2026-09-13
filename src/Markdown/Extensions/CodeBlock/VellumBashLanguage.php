<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\CodeBlock;

use Override;
use Tempest\Highlight\Languages\Bash\BashLanguage;

/**
 * Bash highlighting with command-name tokens so one-line CLIs are not plain text.
 */
final class VellumBashLanguage extends BashLanguage
{
    #[Override]
    public function getPatterns(): array
    {
        return [
            ...parent::getPatterns(),
            new BashCommandPattern,
            new BashVerbPattern,
        ];
    }
}
