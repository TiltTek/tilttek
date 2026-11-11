<?php

declare(strict_types=1);

namespace Tilttek\Dice\Result;

use Override;

/**
 * An `if` or a `switch`, rolled: its subject, the cases that held — by their index from 0, the `if` being one case —,
 * and what it gave: the branch, the list of branches, the default branch, or the subject itself.
 */
final readonly class BranchResult implements NodeResult
{
    /**
     * @param list<int> $matched
     * @param bool      $default whether the default branch gave the result, no case holding
     */
    public function __construct(
        public NodeResult $subject,
        public array $matched,
        public NodeResult $result,
        public bool $default = false,
    ) {
    }

    #[Override]
    public function value(): int
    {
        return $this->result->value();
    }

    #[Override]
    public function output(): int|string|array
    {
        return $this->result->output();
    }

    #[Override]
    public function sets(): array
    {
        return $this->result === $this->subject ? $this->subject->sets() : [...$this->subject->sets(), ...$this->result->sets()];
    }

    /**
     * @return array{type: 'branch', matched: list<int>, default: bool, subject: array<string, mixed>, result: ?array<string, mixed>}
     */
    #[Override]
    public function toArray(): array
    {
        return [
            'type' => 'branch',
            'matched' => $this->matched,
            'default' => $this->default,
            'subject' => $this->subject->toArray(),
            'result' => $this->result === $this->subject ? null : $this->result->toArray(),
        ];
    }
}
