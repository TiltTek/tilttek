<?php

declare(strict_types=1);

namespace Tilttek\Dice\Result;

use Override;
use Tilttek\Dice\Exception\CannotRoll;

/**
 * The result of an instruction before, or one of its items, taken again: its dice are not taken with it.
 */
final readonly class ReferenceResult implements NodeResult
{
    /**
     * @param int|string|list<int|string> $output
     */
    public function __construct(
        public int $instruction,
        public ?int $index,
        private int|string|array $output,
    ) {
    }

    #[Override]
    public function value(): int
    {
        return \is_int($this->output) ? $this->output : throw CannotRoll::notANumber(\is_string($this->output) ? 'a text' : 'a list');
    }

    #[Override]
    public function output(): int|string|array
    {
        return $this->output;
    }

    #[Override]
    public function sets(): array
    {
        return [];
    }

    /**
     * @return array{type: 'reference', instruction: int, index: ?int, output: int|string|list<int|string>}
     */
    #[Override]
    public function toArray(): array
    {
        return ['type' => 'reference', 'instruction' => $this->instruction, 'index' => $this->index, 'output' => $this->output];
    }
}
