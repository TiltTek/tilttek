<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Infrastructure\Messenger;

/**
 * The message that asks a projection to catch up with the journal, up to the entry at that position at least. It
 * carries identifiers only — the projection's name and the entry's position —: the activity itself stays in the
 * journal, which the handler reads.
 */
final readonly class CatchUpProjection
{
    public function __construct(
        public string $projection,
        public int $position,
    ) {
    }
}
