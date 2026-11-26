<?php

declare(strict_types=1);

namespace Tilttek\Guilds;

use DateTimeImmutable;

/**
 * A guild changed: identifiers only — the guild, the member it concerns, the member who acted — and the moment, for
 * the application to relay as a notification or a message on its bus. The member is the new founder of a transfer,
 * and null when the change concerns the guild itself, as an update or a dissolution. The actor is null when the member
 * acted for themselves, or when an erasure did.
 */
final readonly class GuildChanged
{
    public function __construct(
        public string $guild,
        public Change $change,
        public ?string $member,
        public ?string $by,
        public DateTimeImmutable $at,
    ) {
    }
}
