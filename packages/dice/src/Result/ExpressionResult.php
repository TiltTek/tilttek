<?php

declare(strict_types=1);

namespace Tilttek\Dice\Result;

use Tilttek\Dice\Expression\Node;
use Tilttek\Dice\Notation\NotationWriter;

/**
 * One instruction of a pool, rolled: how its result came, node by node, what it gave — a number, a text or a list —,
 * and its total when it gave a number. An instruction whose dice went to a merge gives no total, and says which
 * instruction, numbered from 1, took them.
 */
final readonly class ExpressionResult
{
    public function __construct(
        public Node $expression,
        public NodeResult $result,
        public ?int $mergedInto = null,
    ) {
    }

    /**
     * The number the instruction gave; none for custom dice whose faces carry no value, a text, a list, or dice given
     * to a merge.
     */
    public function total(): ?int
    {
        $output = $this->result->output();

        return null === $this->mergedInto && \is_int($output) ? $output : null;
    }

    /**
     * What the instruction gave: a number, a text — plain or a translation key, never HTML — or a list.
     *
     * @return int|string|list<int|string>
     */
    public function output(): int|string|array
    {
        return $this->result->output();
    }

    /**
     * What the instruction gave, as a sentence writes it.
     */
    public function text(): string
    {
        return Outputs::text($this->output());
    }

    /**
     * @return array{expression: string, total: ?int, output: int|string|list<int|string>, merged_into: ?int, detail: array<string, mixed>}
     */
    public function toArray(): array
    {
        return ['expression' => new NotationWriter()->writeNode($this->expression), 'total' => $this->total(), 'output' => $this->output(), 'merged_into' => $this->mergedInto, 'detail' => $this->result->toArray()];
    }
}
