<?php

declare(strict_types=1);

namespace Tilttek\Dice\Result;

use Override;
use Tilttek\Dice\Expression\MathFunction;

/**
 * A function and the results of its arguments, and the value it gives.
 */
final readonly class FunctionResult implements NodeResult
{
    /**
     * @param list<NodeResult> $arguments
     */
    public function __construct(
        public MathFunction $function,
        public array $arguments,
        private int $value,
    ) {
    }

    #[Override]
    public function value(): int
    {
        return $this->value;
    }

    #[Override]
    public function output(): int
    {
        return $this->value;
    }

    #[Override]
    public function sets(): array
    {
        $sets = [];

        foreach ($this->arguments as $argument) {
            $sets = [...$sets, ...$argument->sets()];
        }

        return $sets;
    }

    /**
     * @return array{type: 'function', function: string, value: int, arguments: list<array<string, mixed>>}
     */
    #[Override]
    public function toArray(): array
    {
        return ['type' => 'function', 'function' => $this->function->value, 'value' => $this->value, 'arguments' => array_map(static fn (NodeResult $argument): array => $argument->toArray(), $this->arguments)];
    }
}
