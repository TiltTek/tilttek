<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets;

use Tilttek\ScoreSheets\Message\MatchResultRecorded;
use Tilttek\ScoreSheets\Scoring\ParticipantScore;
use Tilttek\ScoreSheets\Scoring\Scoreboard;
use Tilttek\ScoreSheets\Scoring\TeamScore;

/**
 * The result of a closed match, as neutral data for a play log or a rating: the game, the places and totals of the
 * participants and of the teams, and the revision — a result of revision 2 or more replaces the one before, after the
 * sheet was reopened.
 */
final readonly class MatchResult
{
    /**
     * @param list<Placement> $participants in rank order
     * @param list<Placement> $teams        in rank order
     */
    public function __construct(
        public MatchId $match,
        public string $game,
        public int $revision,
        public array $participants,
        public array $teams,
        public bool $complete,
    ) {
    }

    public static function of(MatchSheet $sheet, Scoreboard $scoreboard): self
    {
        return new self(
            $sheet->id,
            $sheet->sheet->game,
            $sheet->revision,
            array_map(static fn (ParticipantScore $score): Placement => new Placement($score->participant->value, $score->rank, $score->total), $scoreboard->participants),
            array_map(static fn (TeamScore $score): Placement => new Placement($score->team->value, $score->rank, $score->total, array_column($score->members, 'participant')), $scoreboard->teams),
            $scoreboard->complete,
        );
    }

    /**
     * Whether it replaces a result passed on before.
     */
    public function isCorrection(): bool
    {
        return $this->revision > 1;
    }

    /**
     * The message to dispatch: the match and the revision only, the result read from the store.
     */
    public function message(): MatchResultRecorded
    {
        return new MatchResultRecorded($this->match->value, $this->revision);
    }

    /**
     * @return array{match: string, game: string, revision: int, complete: bool, participants: list<array{id: string, rank: int, total: int, members: list<string>}>, teams: list<array{id: string, rank: int, total: int, members: list<string>}>}
     */
    public function toArray(): array
    {
        return [
            'match' => $this->match->value,
            'game' => $this->game,
            'revision' => $this->revision,
            'complete' => $this->complete,
            'participants' => array_map(static fn (Placement $placement): array => $placement->toArray(), $this->participants),
            'teams' => array_map(static fn (Placement $placement): array => $placement->toArray(), $this->teams),
        ];
    }
}
