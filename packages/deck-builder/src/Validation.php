<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder;

use DateTimeImmutable;

/**
 * What the validation of a deck found: every violation, in the order of the deck's cards after those of its size,
 * the day the format's rules were taken on and the taboo list that applied.
 */
final readonly class Validation
{
    /**
     * @param list<Violation> $violations
     */
    public function __construct(
        public Deck $deck,
        public Ruling $ruling,
        public DateTimeImmutable $on,
        public array $violations,
    ) {
    }

    public function isValid(): bool
    {
        return [] === $this->violations;
    }

    /**
     * The messages of the violations, in their order.
     *
     * @return list<string>
     */
    public function messages(): array
    {
        return array_map(static fn (Violation $violation): string => $violation->message, $this->violations);
    }

    /**
     * The violations of one type.
     *
     * @return list<Violation>
     */
    public function ofType(ViolationType $type): array
    {
        return array_values(array_filter($this->violations, static fn (Violation $violation): bool => $type === $violation->type));
    }
}
