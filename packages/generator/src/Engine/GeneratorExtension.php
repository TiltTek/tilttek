<?php

declare(strict_types=1);

namespace Tilttek\Generator\Engine;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * The generator's functions and filters, in the Twig environment of its templates only: `replace` and `join` take the
 * place of Twig's own, bounded; and the steps a template counts at each turn of a loop and after each assignment.
 *
 * @internal
 */
final class GeneratorExtension extends AbstractExtension
{
    public function __construct(private readonly Producer $producer)
    {
    }

    /**
     * Called by the compiled templates at each step (see `StepNode`).
     */
    public function step(): void
    {
        $this->producer->step();
    }

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('pick', $this->producer->pick(...)),
            new TwigFunction('generate', $this->producer->generate(...)),
            new TwigFunction('roll', $this->producer->roll(...)),
            new TwigFunction('between', $this->producer->between(...)),
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
            new TwigFilter('replace', $this->producer->replace(...)),
            new TwigFilter('join', $this->producer->join(...)),
        ];
    }

    #[Override]
    public function getNodeVisitors(): array
    {
        return [new StepVisitor()];
    }
}
