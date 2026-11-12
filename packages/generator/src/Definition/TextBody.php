<?php

declare(strict_types=1);

namespace Tilttek\Generator\Definition;

use Tilttek\Generator\Exception\InvalidDefinition;

/**
 * A text, from one of the generator's weighted templates, then passed through the generator's filters — `capitalize`,
 * `upper_first`, `elide` — in order.
 */
final readonly class TextBody implements Body
{
    /**
     * @var non-empty-list<Template>
     */
    public array $templates;

    /**
     * @param list<Template> $templates
     * @param list<string>   $filters
     */
    public function __construct(array $templates, public array $filters = [])
    {
        if ([] === $templates) {
            throw new InvalidDefinition('A text generator has at least one template.');
        }

        $this->templates = $templates;
    }
}
