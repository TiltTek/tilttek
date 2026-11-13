<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder;

/**
 * A rule a deck breaks, with a message people read and the values it was built from, for an application that writes
 * its own message or translates it.
 */
final readonly class Violation
{
    /**
     * @param array<string, int|string> $values the values of the message: `card`, `quantity`, `limit`, `size`, `by`…
     */
    public function __construct(
        public ViolationType $type,
        public string $message,
        public ?string $card = null,
        public array $values = [],
    ) {
    }

    /**
     * @return array{type: string, message: string, card: ?string, values: array<string, int|string>}
     */
    public function toArray(): array
    {
        return ['type' => $this->type->value, 'message' => $this->message, 'card' => $this->card, 'values' => $this->values];
    }
}
