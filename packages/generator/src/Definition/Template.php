<?php

declare(strict_types=1);

namespace Tilttek\Generator\Definition;

use Tilttek\Generator\Exception\InvalidDefinition;

/**
 * A Twig template of a generator and its weight among the generator's templates.
 */
final readonly class Template
{
    public function __construct(public string $source, public int $weight = 1)
    {
        if ($weight < 1) {
            throw InvalidDefinition::at($source, 'the weight of a template is a whole number of at least 1');
        }
    }
}
