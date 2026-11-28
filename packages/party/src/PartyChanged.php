<?php

declare(strict_types=1);

namespace Tilttek\Party;

use DateTimeImmutable;

/**
 * A party changed: identifiers only — the party, the member it concerns, the subject (a currency, a loot key or a note
 * key), the reference that acted — and the moment, for the application to relay as a notification or a message on its
 * bus. Amounts, names and texts are never told: the application reads them from the store when it needs them.
 */
final readonly class PartyChanged
{
    public function __construct(
        public string $party,
        public Change $change,
        public ?string $member,
        public ?string $subject,
        public ?string $by,
        public DateTimeImmutable $at,
    ) {
    }
}
