<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Infrastructure\Event;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tilttek\Quests\Duel\Duel;
use Tilttek\Quests\QuestCompleted;
use Tilttek\Quests\QuestObserver;

/**
 * Turns what the projection and the duels decide into events of the application's event dispatcher: the
 * `QuestCompleted` of the library as it is, with the rewards to give, and `DuelWon` and `DuelExpired` around the duel.
 */
final readonly class DispatchingObserver implements QuestObserver
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    #[Override]
    public function completed(QuestCompleted $completed): void
    {
        $this->dispatcher->dispatch($completed);
    }

    #[Override]
    public function duelWon(Duel $duel): void
    {
        $this->dispatcher->dispatch(new DuelWon($duel));
    }

    #[Override]
    public function duelExpired(Duel $duel): void
    {
        $this->dispatcher->dispatch(new DuelExpired($duel));
    }
}
