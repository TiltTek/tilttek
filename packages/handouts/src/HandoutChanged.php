<?php

declare(strict_types=1);

namespace Tilttek\Handouts;

use DateTimeImmutable;

/**
 * A handout changed: identifiers only — the handout, its table, the player it concerns or none for everyone, the
 * reference that acted — and the moment, for the application to relay as a notification, a push to the players'
 * screens or a message on its bus. Titles, texts and files are never told: the application reads them from the store
 * when it needs them, and checks there that the receiver may see them.
 */
final readonly class HandoutChanged
{
    public function __construct(
        public string $handout,
        public string $table,
        public Change $change,
        public ?string $player,
        public ?string $by,
        public DateTimeImmutable $at,
    ) {
    }
}
