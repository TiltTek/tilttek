<?php

declare(strict_types=1);

namespace Tilttek\Generator\Definition;

use Tilttek\Generator\Exception\InvalidDefinition;

/**
 * A field filled by another generator: one result, or a list of results without duplicates when a count is given.
 */
final readonly class GenerateField implements Field
{
    public function __construct(public string $generator, public ?int $count = null)
    {
        if (null !== $count && $count < 1) {
            throw InvalidDefinition::at($generator, 'the count of a generated field is a whole number of at least 1');
        }
    }
}
