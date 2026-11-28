<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Choice;

use Tilttek\GameCatalog\ExternalId;
use Tilttek\GameCatalog\PlayerCountChoice;
use Tilttek\GameCatalog\Range;

/**
 * A game suggested for a table, and why: the numbers of players and the minutes its publisher states, who can bring
 * it, and what the members of the catalogue voted for this number of players, with the number of votes the
 * recommendation rests on.
 */
final readonly class Suggestion
{
    /**
     * @param list<string> $owners        the members who can bring it, in the order the shelves gave them
     * @param bool         $inLibrary     whether the club or the store keeps it
     * @param ?Range       $playtime      the minutes the publisher states; `null` when the catalogue does not know them
     * @param ?int         $averageRating the average rating of this number of players in hundredths, 100 to 500
     * @param ?ExternalId  $boardGameGeek the identifier of the game on BoardGameGeek, to link to; nothing else of it
     */
    public function __construct(
        public string $game,
        public string $name,
        public Range $players,
        public ?Range $playtime,
        public array $owners,
        public bool $inLibrary,
        public ?PlayerCountChoice $choice,
        public int $choiceVotes,
        public ?int $averageRating,
        public int $ratingVotes,
        public ?ExternalId $boardGameGeek,
    ) {
    }

    /**
     * The link to the page of the game on BoardGameGeek, or `null`.
     */
    public function boardGameGeekLink(): ?string
    {
        return $this->boardGameGeek?->link();
    }
}
