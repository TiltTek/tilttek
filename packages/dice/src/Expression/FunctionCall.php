<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * A function and its arguments: `ceil(1d6/3)`, `max(1d20, 1d20)`, `repeat(4d6kh3, 6)`.
 */
final readonly class FunctionCall implements Node
{
    /**
     * @var non-empty-list<Node>
     */
    public array $arguments;

    public function __construct(
        public MathFunction $function,
        Node $argument,
        Node ...$arguments,
    ) {
        $count = 1 + \count($arguments);

        if ($function->isVariadic() ? $count < $function->arity() : $function->arity() !== $count) {
            $takes = match (true) {
                $function->isVariadic() => 'two arguments or more',
                2 === $function->arity() => 'two arguments',
                default => 'one argument',
            };

            throw InvalidDefinition::because(\sprintf('%s() takes %s; %d given.', $function->value, $takes, $count));
        }

        $this->arguments = [$argument, ...array_values($arguments)];
    }
}
