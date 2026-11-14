<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Application\Event;

use Phil\Resource\ResourceReference;

/**
 * A replayable roll was kept in the journal: `#[AsEventListener] public function __invoke(RollRecorded $event)`. The
 * event carries identifiers only — the roll's, its owner's and its context's —: a listener reads the roll from the
 * journal, and a message it sends on carries the same.
 *
 * The event is dispatched at least once: a listener may hear it again after another listener failed, and must be
 * idempotent; `key()` names the roll, the same each time.
 */
final readonly class RollRecorded
{
    public function __construct(
        public string $id,
        public ?ResourceReference $owner = null,
        public ?ResourceReference $context = null,
    ) {
    }

    /**
     * The stable key of the event: the roll's identifier, by which a listener recognises an event it already acted on.
     */
    public function key(): string
    {
        return $this->id;
    }
}
