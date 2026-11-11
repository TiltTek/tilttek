<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Tests\Functional\App;

use Phil\Resource\ResourceReference;
use Tilttek\Quests\QuestCompleted;
use Tilttek\Quests\Reward;
use Tilttek\QuestsBundle\Infrastructure\Event\DuelExpired;
use Tilttek\QuestsBundle\Infrastructure\Event\DuelWon;

/**
 * A listener that writes down the events the bundle dispatches, one line each.
 */
final class Recorder
{
    /**
     * @var list<string>
     */
    public array $events = [];

    /**
     * @return list<string>
     */
    public function events(): array
    {
        return $this->events;
    }

    public function completed(QuestCompleted $completed): void
    {
        $this->events[] = \sprintf(
            'completed %s for %s: %s',
            $completed->quest->code,
            implode(',', array_map(static fn (ResourceReference $member): string => $member->toString(), $completed->beneficiaries)),
            implode(',', array_map(static fn (Reward $reward): string => $reward->kind.' '.$reward->code.' '.$reward->amount, $completed->rewards())),
        );
    }

    public function won(DuelWon $event): void
    {
        $this->events[] = 'won '.$event->duel->key;
    }

    public function expired(DuelExpired $event): void
    {
        $this->events[] = 'expired '.$event->duel->key;
    }
}
