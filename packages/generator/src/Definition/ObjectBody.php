<?php

declare(strict_types=1);

namespace Tilttek\Generator\Definition;

use Tilttek\Generator\Exception\InvalidDefinition;

/**
 * A structured result, such as an inn with its name, its keeper, its staff and its menu: each field filled in order,
 * each later field seeing the earlier ones as variables.
 */
final readonly class ObjectBody implements Body
{
    /**
     * @param array<string, Field> $fields
     */
    public function __construct(public array $fields)
    {
        if ([] === $fields) {
            throw new InvalidDefinition('A structured generator has at least one field.');
        }
    }
}
