<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Tests\Benchmark;

use Tilttek\Tournament\Outcome;
use Tilttek\Tournament\Records;
use Tilttek\Tournament\Result;
use Tilttek\Tournament\Round;
use Tilttek\Tournament\RoundRobin\BergerTable;
use Tilttek\Tournament\Scoring;
use Tilttek\Tournament\Standings\StandingsCalculator;
use Tilttek\Tournament\Swiss\SwissPairer;
use Tilttek\Tournament\Tiebreak\Tiebreakers;

/**
 * What an organiser waits for: a Swiss round of 16 participants paired exactly, a round of 64 and of 512 paired by the
 * bounded search, the Berger table of a pool of 20, and the standings of 512 participants with three tie-breakers.
 */
final readonly class PairingBench
{
    /** @var list<string> */
    private array $sixteen;

    /** @var list<string> */
    private array $sixtyFour;

    /** @var list<string> */
    private array $field;

    private Records $sixteenRecords;

    private Records $sixtyFourRecords;

    private Records $fieldRecords;

    public function __construct()
    {
        $this->sixteen = $this->participants(16);
        $this->sixtyFour = $this->participants(64);
        $this->field = $this->participants(512);
        $this->sixteenRecords = $this->played($this->sixteen);
        $this->sixtyFourRecords = $this->played($this->sixtyFour);
        $this->fieldRecords = $this->played($this->field);
    }

    /**
     * @Revs(10)
     *
     * @Iterations(3)
     */
    public function benchSwissSixteenExact(): void
    {
        new SwissPairer()->pair($this->sixteen, $this->sixteenRecords);
    }

    /**
     * @Revs(10)
     *
     * @Iterations(3)
     */
    public function benchSwissSixtyFourBounded(): void
    {
        new SwissPairer()->pair($this->sixtyFour, $this->sixtyFourRecords);
    }

    /**
     * @Revs(2)
     *
     * @Iterations(3)
     */
    public function benchSwissFiveHundredTwelveBounded(): void
    {
        new SwissPairer()->pair($this->field, $this->fieldRecords);
    }

    /**
     * @Revs(10)
     *
     * @Iterations(3)
     */
    public function benchBergerTableOfTwenty(): void
    {
        BergerTable::rounds($this->participants(20), 2);
    }

    /**
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchStandingsOfFiveHundredTwelve(): void
    {
        new StandingsCalculator(new Tiebreakers()->get(['buchholz', 'sonneborn-berger', 'wins']), 'seed')->compute($this->field, $this->fieldRecords);
    }

    /**
     * @return list<string>
     */
    private function participants(int $count): array
    {
        return array_map(static fn (int $index): string => 'p'.$index, range(1, $count));
    }

    /**
     * The records after three rounds: the first round paired by the pairer, every higher ranked participant winning.
     *
     * @param list<string> $participants
     */
    private function played(array $participants): Records
    {
        $pairer = new SwissPairer();
        $scoring = new Scoring();
        $rounds = [];
        $results = [];

        for ($number = 1; $number <= 3; ++$number) {
            $records = Records::build($participants, $rounds, $results, $scoring);
            $round = new Round('swiss', $number, $pairer->pair($participants, $records));

            foreach ($round->pairings as $pairing) {
                if (null !== $pairing->second) {
                    $results[$round->key($pairing->table)] = new Result(0 === $number % 2 ? Outcome::SecondWins : Outcome::FirstWins);
                }
            }

            $rounds[] = $round;
        }

        return Records::build($participants, $rounds, $results, $scoring);
    }
}
