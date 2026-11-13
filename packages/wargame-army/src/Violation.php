<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy;

/**
 * A rule an army list breaks, with a message people read and the values it was built from, for an application that
 * writes its own message or translates it.
 */
final readonly class Violation
{
    /**
     * @param array<string, int|string> $values the values of the message: `detachment` and `unit` from 1, `count`,
     *                                          `min`, `max`, `points`…
     */
    public function __construct(
        public Rule $rule,
        public string $message,
        public array $values = [],
    ) {
    }

    /**
     * @return array{rule: string, message: string, values: array<string, int|string>}
     */
    public function toArray(): array
    {
        return ['rule' => $this->rule->value, 'message' => $this->message, 'values' => $this->values];
    }
}
