<?php

declare(strict_types=1);

namespace Tilttek\Celebrations;

use DateTimeImmutable;

/**
 * An inbox changed: identifiers only — the member, the source and the occurrence of the entry, none for a change of
 * the whole inbox — and the moment, for the application to relay as a notification or a message on its bus. Parameters
 * and preferences are never told: the application reads them from the store when it needs them.
 */
final readonly class CelebrationChanged
{
    public function __construct(
        public string $member,
        public Change $change,
        public ?string $source,
        public ?string $occurrence,
        public DateTimeImmutable $at,
    ) {
    }
}
