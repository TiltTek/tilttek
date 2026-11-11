<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Infrastructure\Messenger;

use Override;
use Symfony\Component\Messenger\MessageBusInterface;
use Tilttek\Activity\Entry;
use Tilttek\Activity\JournalObserver;
use Tilttek\Activity\Projection\Projections;

/**
 * Keeps the projections up to date out of the request: each new entry sends, for every projection whose filter reads
 * it, a `CatchUpProjection` message on the bus, which the application routes to an asynchronous transport.
 *
 * The journal is the outbox: the entry is written before the message is sent, so a message lost on the way is made up
 * by the projection's next message, which catches it up past every entry it has not seen, or by
 * `tilttek:activity:catch-up`. The checkpoints are the inbox: a message delivered twice, or after a later one, finds
 * the projection already past its entry and applies nothing.
 */
final readonly class MessengerProjections implements JournalObserver
{
    public function __construct(
        private MessageBusInterface $bus,
        private Projections $projections,
    ) {
    }

    #[Override]
    public function appended(Entry $entry): void
    {
        foreach ($this->projections->all() as $projection) {
            if ($projection->filter()->matches($entry->activity)) {
                $this->bus->dispatch(new CatchUpProjection($projection->name(), $entry->position));
            }
        }
    }
}
