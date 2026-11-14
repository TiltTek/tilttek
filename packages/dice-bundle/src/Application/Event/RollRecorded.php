<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Application\Event;

use Phil\Resource\ResourceReference;

/**
 * A replayable roll was kept in the journal: `#[AsEventListener] public function __invoke(RollRecorded $event)`. The
 * event carries identifiers only — the roll's, its owner's and its context's —: a listener reads the roll from the
 * journal, and a message it sends on carries the same.
 */
final readonly class RollRecorded
{
    public function __construct(
        public string $id,
        public ?ResourceReference $owner = null,
        public ?ResourceReference $context = null,
    ) {
    }
}
