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
     *
     * @throws InvalidDefinition when there is no template, or their weights add up to more than an integer holds
     */
    public function __construct(array $templates, public array $filters = [])
    {
        if ([] === $templates) {
            throw new InvalidDefinition('A text generator has at least one template.');
        }

        $total = 0;

        foreach ($templates as $template) {
            if ($template->weight > \PHP_INT_MAX - $total) {
                throw new InvalidDefinition(\sprintf('The weights of the templates of a text generator add up to at most %d.', \PHP_INT_MAX));
            }

            $total += $template->weight;
        }

        $this->templates = $templates;
    }
}
