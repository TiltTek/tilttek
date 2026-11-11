<?php

declare(strict_types=1);

namespace Tilttek\Quests;

use Phil\Resource\ResourceReference;

/**
 * A quest accomplished, with the rewards it declares and the members they go to: the member, the winner of a duel,
 * the members of the team who still follow it, or every member whose activity counted towards a challenge of the
 * community. The application gives the rewards through the packages it has.
 */
final readonly class QuestCompleted
{
    /**
     * @param list<ResourceReference> $beneficiaries
     */
    public function __construct(
        public Quest $quest,
        public Completion $completion,
        public array $beneficiaries,
    ) {
    }

    /**
     * @return list<Reward>
     */
    public function rewards(): array
    {
        return $this->quest->rewards;
    }
}
