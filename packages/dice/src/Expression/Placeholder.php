<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * A placeholder of a sentence, replaced by a result when the sentence is rolled: `%1`, `%2`, `%3`, `$2`, `$2[0]`,
 * `@1`.
 */
final readonly class Placeholder
{
    public function __construct(
        public PlaceholderKind $kind,
        public ?int $instruction = null,
        public ?int $index = null,
    ) {
        if ($kind->namesInstruction() !== (null !== $instruction)) {
            throw InvalidDefinition::because(\sprintf('The placeholder %s %s.', $kind->value, $kind->namesInstruction() ? 'names its instruction' : 'names no instruction'));
        }

        if (null !== $instruction && $instruction < 1) {
            throw InvalidDefinition::because(\sprintf('Instructions are numbered from 1; %d is not one.', $instruction));
        }

        if (null !== $index && (PlaceholderKind::Result !== $kind || $index < 0)) {
            throw InvalidDefinition::because('Only "$" takes an item, numbered from 0.');
        }
    }
}
