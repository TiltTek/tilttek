<?php

declare(strict_types=1);

namespace Tilttek\Kudos;

use DateTimeImmutable;

/**
 * Thanks were given: identifiers only — the key, the giver, the receiver, the message's name, the object — and the
 * moment, for the application to relay as a notification or a message on its bus.
 */
final readonly class KudosGiven
{
    public function __construct(
        public string $key,
        public string $giver,
        public string $receiver,
        public string $message,
        public ?string $object,
        public DateTimeImmutable $givenAt,
    ) {
    }
}
