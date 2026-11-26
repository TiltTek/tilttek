<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets;

use Tilttek\ScoreSheets\Contract\MatchSheetStore;
use Tilttek\ScoreSheets\Exception\ConcurrentChange;
use Tilttek\ScoreSheets\Exception\IllegalTransition;
use Tilttek\ScoreSheets\Exception\InvalidEntry;
use Tilttek\ScoreSheets\Exception\UnknownMatch;
use Tilttek\ScoreSheets\Scoring\Scoreboard;
use Tilttek\ScoreSheets\Scoring\Scorer;

/**
 * The sheets of matches, through a store: prepare a sheet, publish it, enter scores against the version the scorer
 * saw — the scoreboard computed again at each entry —, close it to pass its result on, and reopen it when a score was
 * entered too early. It keeps no state between two calls.
 */
final readonly class ScoreKeeper
{
    public function __construct(
        private MatchSheetStore $store,
        private Scorer $scorer = new Scorer(),
    ) {
    }

    /**
     * @throws ConcurrentChange when the store already holds a sheet for this match
     */
    public function prepare(MatchId $id, SheetDefinition $sheet, Entries $entries): MatchSheet
    {
        $match = new MatchSheet($id, $sheet, $entries);
        $this->store->save($match);

        return $match;
    }

    /**
     * Shows the prepared sheet to the players.
     *
     * @throws UnknownMatch
     * @throws ConcurrentChange
     * @throws IllegalTransition when the sheet is not being prepared
     */
    public function publish(MatchId $id, int $version): MatchSheet
    {
        return $this->move($id, $version, SheetStatus::Prepared, SheetStatus::Published, 'Only a prepared sheet is published.');
    }

    /**
     * Enters one value — or erases it, with null — and returns the scoreboard computed again.
     *
     * @throws UnknownMatch
     * @throws ConcurrentChange
     * @throws IllegalTransition when the sheet is closed
     * @throws InvalidEntry      when the participant, the category or the value is refused
     */
    public function record(MatchId $id, int $version, ParticipantId $participant, string $category, ?int $value): Scoreboard
    {
        $match = $this->at($id, $version);

        if (SheetStatus::Closed === $match->status) {
            throw IllegalTransition::because('A closed sheet takes no score: reopen it first.');
        }

        $entries = null === $value
            ? $match->entries->without($match->sheet, $participant, $category)
            : $match->entries->with($match->sheet, $participant, $category, $value);
        $scoreboard = $this->scorer->score($match->sheet, $entries);
        $this->store->save($match->withEntries($entries));

        return $scoreboard;
    }

    /**
     * Closes the published sheet and returns its result, to pass on with `MatchResult::message()`.
     *
     * @throws UnknownMatch
     * @throws ConcurrentChange
     * @throws IllegalTransition when the sheet is not published
     */
    public function close(MatchId $id, int $version): MatchResult
    {
        $closed = $this->move($id, $version, SheetStatus::Published, SheetStatus::Closed, 'Only a published sheet is closed.');

        return MatchResult::of($closed, $this->scorer->score($closed->sheet, $closed->entries));
    }

    /**
     * Opens a closed sheet again to correct it; closing it again passes on a result of the next revision.
     *
     * @throws UnknownMatch
     * @throws ConcurrentChange
     * @throws IllegalTransition when the sheet is not closed
     */
    public function reopen(MatchId $id, int $version): MatchSheet
    {
        return $this->move($id, $version, SheetStatus::Closed, SheetStatus::Published, 'Only a closed sheet is reopened.');
    }

    /**
     * @throws UnknownMatch
     */
    public function sheet(MatchId $id): MatchSheet
    {
        return $this->store->find($id) ?? throw UnknownMatch::because(\sprintf('The store holds no sheet for the match "%s".', $id->value));
    }

    /**
     * @throws UnknownMatch
     */
    public function scoreboard(MatchId $id): Scoreboard
    {
        $match = $this->sheet($id);

        return $this->scorer->score($match->sheet, $match->entries);
    }

    private function move(MatchId $id, int $version, SheetStatus $from, SheetStatus $to, string $refusal): MatchSheet
    {
        $match = $this->at($id, $version);

        if ($from !== $match->status) {
            throw IllegalTransition::because($refusal);
        }

        $moved = $match->withStatus($to);
        $this->store->save($moved);

        return $moved;
    }

    private function at(MatchId $id, int $version): MatchSheet
    {
        $match = $this->sheet($id);

        if ($match->version !== $version) {
            throw ConcurrentChange::because(\sprintf('The sheet of the match "%s" is at version %d, not %d.', $id->value, $match->version, $version));
        }

        return $match;
    }
}
