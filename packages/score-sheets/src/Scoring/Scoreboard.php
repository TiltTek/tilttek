<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Scoring;

use Tilttek\ScoreSheets\ParticipantId;

/**
 * The scores of a match at one moment: every participant and every team, in rank order, and whether every value was
 * entered.
 */
final readonly class Scoreboard
{
    /**
     * @param list<ParticipantScore> $participants in rank order, ties in the order of the sheet
     * @param list<TeamScore>        $teams        in rank order, ties in the declared order
     */
    public function __construct(
        public string $game,
        public array $participants,
        public array $teams,
        public bool $complete,
    ) {
    }

    public function participant(ParticipantId $participant): ?ParticipantScore
    {
        foreach ($this->participants as $score) {
            if ($score->participant->value === $participant->value) {
                return $score;
            }
        }

        return null;
    }

    /**
     * @return array{game: string, complete: bool, participants: list<array<string, mixed>>, teams: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'game' => $this->game,
            'complete' => $this->complete,
            'participants' => array_map(static fn (ParticipantScore $score): array => $score->toArray(), $this->participants),
            'teams' => array_map(static fn (TeamScore $score): array => $score->toArray(), $this->teams),
        ];
    }
}
