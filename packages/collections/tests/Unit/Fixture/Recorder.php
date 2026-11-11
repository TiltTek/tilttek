<?php

declare(strict_types=1);

namespace Tilttek\Collections\Tests\Unit\Fixture;

use Override;
use Tilttek\Collections\CollectionObserver;
use Tilttek\Collections\Copy;
use Tilttek\Collections\Reward;
use Tilttek\Collections\SetCompleted;

/**
 * An observer that writes down what it is told, in a few words per event.
 */
final class Recorder implements CollectionObserver
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

    #[Override]
    public function granted(Copy $copy): void
    {
        $this->events[] = \sprintf('granted %s to %s', $copy->item, $copy->owner->toString());
    }

    #[Override]
    public function setCompleted(SetCompleted $completed): void
    {
        $this->events[] = \sprintf(
            'completed %s by %s at %s: %s',
            $completed->set->code,
            $completed->completion->member->toString(),
            $completed->completion->completedAt->format('Y-m-d H:i'),
            implode(', ', array_map(static fn (Reward $reward): string => \sprintf('%s %s %d', $reward->kind, $reward->code, $reward->amount), $completed->set->rewards)),
        );
    }
}
