<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Tests\Functional\App;

use Tilttek\Collections\Reward;
use Tilttek\Collections\SetCompleted;
use Tilttek\CollectionsBundle\Infrastructure\Event\CopyGranted;

/**
 * A listener that writes down the events the bundle dispatches, one line each.
 */
final class Recorder
{
    /**
     * @var list<string>
     */
    private array $events = [];

    /**
     * @return list<string>
     */
    public function events(): array
    {
        return $this->events;
    }

    public function granted(CopyGranted $event): void
    {
        $this->events[] = \sprintf('granted %s to %s', $event->copy->item, $event->copy->owner->toString());
    }

    public function completed(SetCompleted $event): void
    {
        $this->events[] = \sprintf(
            'completed %s by %s: %s',
            $event->set->code,
            $event->completion->member->toString(),
            implode(',', array_map(static fn (Reward $reward): string => $reward->kind.' '.$reward->code.' '.$reward->amount, $event->set->rewards)),
        );
    }
}
