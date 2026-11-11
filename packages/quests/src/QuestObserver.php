<?php

declare(strict_types=1);

namespace Tilttek\Quests;

use Tilttek\Quests\Duel\Duel;

/**
 * Told what the projection and the duels decide: a quest accomplished, a duel won, a duel whose time ran out.
 */
interface QuestObserver
{
    public function completed(QuestCompleted $completed): void;

    public function duelWon(Duel $duel): void;

    public function duelExpired(Duel $duel): void;
}
