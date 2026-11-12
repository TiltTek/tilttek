<?php

declare(strict_types=1);

namespace Tilttek\Generator\Engine;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * The generator's functions and filters, in the Twig environment of its templates only.
 *
 * @internal
 */
final class GeneratorExtension extends AbstractExtension
{
    public function __construct(private readonly Producer $producer)
    {
    }

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('pick', $this->producer->pick(...)),
            new TwigFunction('generate', $this->producer->generate(...)),
            new TwigFunction('roll', $this->producer->roll(...)),
        ];
    }

    #[Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('upper_first', $this->producer->upperFirst(...)),
            new TwigFilter('elide', $this->producer->elide(...)),
            new TwigFilter('article', $this->producer->article(...)),
            new TwigFilter('agree', $this->producer->agree(...)),
            new TwigFilter('plural', $this->producer->plural(...)),
        ];
    }
}
