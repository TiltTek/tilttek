<?php

declare(strict_types=1);

namespace Tilttek\Generator\Definition;

use Tilttek\Generator\Draw;
use Tilttek\Generator\Exception\InvalidDefinition;

/**
 * A generator: its name, what it returns, the variables its templates share — set once per result, before the body —,
 * the language of its grammar filters, and the state it carries from one result to the next, if it has one: a table
 * with a memory.
 */
final readonly class GeneratorDefinition
{
    /**
     * The names the templates of a generator with a state read it by, which a variable or a field cannot take.
     */
    public const array STATE_NAMES = ['state', 'previous'];

    /**
     * @param array<string, string>     $vars  templates, by variable name, rendered in order
     * @param array<string, StateValue> $state the values of the state, by name, given in order before the variables
     */
    public function __construct(
        public string $name,
        public Body $body,
        public array $vars = [],
        public ?string $locale = null,
        public array $state = [],
    ) {
        if (\count($state) > Draw::STATE_VALUES) {
            throw InvalidDefinition::at($name, \sprintf('a state holds at most %d values', Draw::STATE_VALUES));
        }

        foreach (array_keys($state) as $value) {
            if (1 !== preg_match('/^[A-Za-z_]\w*$/D', $value)) {
                throw InvalidDefinition::at($name, \sprintf('"%s" is not a name of a state value: a letter or "_", then letters, digits or "_"', $value));
            }
        }

        $taken = [...array_keys($vars), ...array_keys($body instanceof ObjectBody ? $body->fields : [])];

        if ([] !== $state && [] !== array_intersect($taken, self::STATE_NAMES)) {
            throw InvalidDefinition::at($name, 'a generator with a state names no variable or field "state" or "previous"');
        }
    }

    /**
     * Whether the generator carries a state from one result to the next.
     */
    public function hasState(): bool
    {
        return [] !== $this->state;
    }
}
