<?php

declare(strict_types=1);

namespace Tilttek\Generator\Engine;

use Override;
use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Node;

/**
 * One step of a result, compiled into a template at each turn of a loop and after each assignment: the generator counts
 * it and checks its limits of steps, memory and time there.
 *
 * @internal
 */
#[YieldReady]
final class StepNode extends Node
{
    public function __construct(int $lineno)
    {
        parent::__construct([], [], $lineno);
    }

    #[Override]
    public function compile(Compiler $compiler): void
    {
        $compiler->write('$this->extensions[')->string(GeneratorExtension::class)->raw("]->step();\n");
    }
}
