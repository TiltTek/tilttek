<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Choice;

use Tilttek\GameCatalog\Catalog;
use Tilttek\GameCatalog\Exception\EntryNotFound;
use Tilttek\GameCatalog\IdentifierScheme;
use Tilttek\GameCatalog\PlayerCountChoice;
use Tilttek\GameCatalog\Range;
use Tilttek\GameNight\Contract\ShelfReader;

/**
 * Suggests games for a table, among the games the members can bring and those the club or the store keeps: a game is
 * suggested when its publisher states the number of players, and it ends within the minutes there are.
 *
 * The suggestions come in this order: the games the members of the catalogue voted best for this number of players,
 * then recommended, then without a vote, then not recommended; among them, the games whose minutes are known first;
 * then the games most members can bring; then by name. A choice from one vote ranks with a choice from fifty: each
 * suggestion carries the number of votes it rests on, to be shown beside it.
 */
final readonly class GameChooser
{
    /** The most games read from the shelves of the members. */
    public const int MAX_SHELF_GAMES = 1_000;

    public function __construct(private Catalog $catalog, private ShelfReader $shelves)
    {
    }

    /**
     * @return list<Suggestion> at most the limit of the criteria
     */
    public function suggest(Criteria $criteria): array
    {
        $owners = array_fill_keys($criteria->library, []);
        $read = 0;

        if ([] !== $criteria->owners) {
            foreach ($this->shelves->games($criteria->owners, self::MAX_SHELF_GAMES) as $owned) {
                if (++$read > self::MAX_SHELF_GAMES) {
                    break;
                }

                if (\in_array($owned->owner, $criteria->owners, true)) {
                    $owners[$owned->game][] = $owned->owner;
                }
            }
        }

        $suggestions = [];

        foreach ($owners as $game => $members) {
            $suggestion = $this->suggestion($game, array_values(array_unique($members)), \in_array($game, $criteria->library, true), $criteria);

            if ($suggestion instanceof Suggestion) {
                $suggestions[] = $suggestion;
            }
        }

        usort($suggestions, $this->compare(...));

        return \array_slice($suggestions, 0, $criteria->limit);
    }

    /**
     * @param list<string> $owners
     */
    private function suggestion(string $game, array $owners, bool $inLibrary, Criteria $criteria): ?Suggestion
    {
        try {
            $sheet = $this->catalog->sheet($game);
        } catch (EntryNotFound) {
            return null;
        }

        $details = $sheet->details();

        if ($sheet->entry->hidden || !$details->players instanceof Range || !$details->players->contains($criteria->players)) {
            return null;
        }

        if ($details->playtime instanceof Range && $details->playtime->max > $criteria->minutes) {
            return null;
        }

        $votes = $sheet->results->playerCount($criteria->players);

        return new Suggestion(
            $game,
            $details->name,
            $details->players,
            $details->playtime,
            $owners,
            $inLibrary,
            $votes?->choice(),
            $votes?->choiceVotes() ?? 0,
            $votes?->averageRating(),
            $votes->ratingVotes ?? 0,
            $sheet->entry->identifier(IdentifierScheme::BoardGameGeek),
        );
    }

    private function compare(Suggestion $a, Suggestion $b): int
    {
        return [$this->rank($a->choice), !$a->playtime instanceof Range, -$this->available($a), $a->name, $a->game]
            <=> [$this->rank($b->choice), !$b->playtime instanceof Range, -$this->available($b), $b->name, $b->game];
    }

    private function rank(?PlayerCountChoice $choice): int
    {
        return (int) array_search($choice, [PlayerCountChoice::Best, PlayerCountChoice::Recommended, null, PlayerCountChoice::NotRecommended], true);
    }

    /**
     * How many can bring the game: its owners, and the club or the store when it keeps it.
     */
    private function available(Suggestion $suggestion): int
    {
        return \count($suggestion->owners) + ($suggestion->inLibrary ? 1 : 0);
    }
}
