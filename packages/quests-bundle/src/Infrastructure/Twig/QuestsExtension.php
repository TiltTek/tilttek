<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Infrastructure\Twig;

use Phil\Resource\ResourceReference;
use Tilttek\Quests\Duel\Duels;
use Tilttek\Quests\Duel\DuelStanding;
use Tilttek\Quests\Quests;
use Tilttek\Quests\QuestStatus;
use Twig\Attribute\AsTwigFunction;

/**
 * The quests in the application's templates: `tilttek_quests_board('member:44')` where every quest stands for a
 * member, for the list; `tilttek_quests_status('member:44', 'winter-reading')` one quest, for its detail;
 * `tilttek_quests_duel('duel:812')` a duel and how far each side is.
 */
final readonly class QuestsExtension
{
    public function __construct(
        private Quests $quests,
        private Duels $duels,
        private string $domain,
    ) {
    }

    /**
     * The translation domain of the templates' texts and of the quests' names.
     */
    #[AsTwigFunction('tilttek_quests_domain')]
    public function domain(): string
    {
        return $this->domain;
    }

    /**
     * @return list<QuestStatus>
     */
    #[AsTwigFunction('tilttek_quests_board')]
    public function board(string $member): array
    {
        return $this->quests->board(ResourceReference::fromString($member));
    }

    #[AsTwigFunction('tilttek_quests_status')]
    public function status(string $member, string $quest): QuestStatus
    {
        return $this->quests->status(ResourceReference::fromString($member), $quest);
    }

    #[AsTwigFunction('tilttek_quests_duel')]
    public function duel(string $key): DuelStanding
    {
        return $this->duels->standing($key);
    }
}
