<?php

declare(strict_types=1);

namespace Tilttek\Tournament;

/**
 * The records of the participants of a stage, built from its rounds and the results kept: a table without a result yet
 * is left out, and a bye scores the points of a bye while a rest scores none.
 */
final readonly class Records
{
    /** @var array<string, int> the points of each participant, by participant */
    private array $points;

    /**
     * @param array<string, Record> $records by participant
     */
    private function __construct(
        private array $records,
    ) {
        $this->points = array_map(static fn (Record $record): int => $record->points(), $records);
    }

    /**
     * @param list<string>          $participants every participant of the stage, those who withdrew included
     * @param list<Round>           $rounds       in number order
     * @param array<string, Result> $results      by the key of the table, `Round::key()`
     */
    public static function build(array $participants, array $rounds, array $results, Scoring $scoring): self
    {
        $games = array_fill_keys($participants, []);

        foreach ($rounds as $round) {
            foreach ($round->pairings as $pairing) {
                if (null === $pairing->second) {
                    $games[$pairing->first][] = new Game($round->number, null, true, $pairing->rest ? 0 : $scoring->byePoints(), rest: $pairing->rest);

                    continue;
                }

                $result = $results[$round->key($pairing->table)] ?? null;

                if (null === $result) {
                    continue;
                }

                [$firstPoints, $secondPoints] = $scoring->points($result->outcome);
                $games[$pairing->first][] = new Game($round->number, $pairing->second, true, $firstPoints, $result->outcome, $result->firstMission ?? 0);
                $games[$pairing->second][] = new Game($round->number, $pairing->first, false, $secondPoints, $result->outcome, $result->secondMission ?? 0);
            }
        }

        $records = [];

        foreach ($games as $participant => $played) {
            $records[$participant] = new Record($participant, $played);
        }

        return new self($records);
    }

    /**
     * The record of a participant; an empty one for a participant without a match.
     */
    public function of(string $participant): Record
    {
        return $this->records[$participant] ?? new Record($participant);
    }

    public function points(string $participant): int
    {
        return $this->points[$participant] ?? 0;
    }

    /**
     * @return list<string>
     */
    public function participants(): array
    {
        return array_values(array_map(static fn (Record $record): string => $record->participant, $this->records));
    }
}
