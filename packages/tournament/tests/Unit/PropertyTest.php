<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tilttek\Tournament\Pairing;
use Tilttek\Tournament\Progress;
use Tilttek\Tournament\Result;
use Tilttek\Tournament\Round;
use Tilttek\Tournament\Stage;
use Tilttek\Tournament\Store\InMemoryTournamentStore;
use Tilttek\Tournament\Swiss\BoundedMatcher;
use Tilttek\Tournament\Swiss\ExactMatcher;
use Tilttek\Tournament\Swiss\SwissPairer;
use Tilttek\Tournament\Tournament;
use Tilttek\Tournament\Tournaments;

/**
 * Whole tournaments played with results drawn from a fixed seed: the properties hold for every one of them, and the same
 * seed gives the same tournament again.
 */
#[CoversClass(Tournaments::class)]
#[CoversClass(Progress::class)]
#[CoversClass(SwissPairer::class)]
#[CoversClass(ExactMatcher::class)]
#[CoversClass(BoundedMatcher::class)]
final class PropertyTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function swissFields(): iterable
    {
        foreach ([[4, 3], [5, 4], [6, 5], [7, 6], [8, 5], [9, 5], [12, 6], [15, 7], [16, 7], [17, 5], [24, 6], [33, 6], [64, 7]] as [$players, $rounds]) {
            foreach ([1, 2, 3] as $draw) {
                yield \sprintf('%d players, %d rounds, draw %d', $players, $rounds, $draw) => [$players, $rounds, $draw];
            }
        }
    }

    #[DataProvider('swissFields')]
    public function testASwissStageNeverRepeatsAMeetingOrAByeWhenItCanBeAvoided(int $players, int $rounds, int $draw): void
    {
        $met = [];
        $byes = [];
        $repeats = 0;

        foreach ($this->playSwiss($players, $rounds, $draw) as $round) {
            $seated = [];
            $repeated = false;

            foreach ($round->pairings as $pairing) {
                $seated[] = $pairing->first;

                if (null === $pairing->second) {
                    $repeated = $repeated || isset($byes[$pairing->first]);

                    continue;
                }

                $seated[] = $pairing->second;
                $repeated = $repeated || isset($met[$pairing->first][$pairing->second]);
            }

            self::assertCount($players, $seated);
            self::assertCount($players, array_unique($seated));
            self::assertLessThanOrEqual(1, \count(array_filter($round->pairings, static fn (Pairing $pairing): bool => $pairing->isBye())));

            if ($repeated) {
                ++$repeats;
                self::assertFalse(self::avoidable($seated, $met, $byes), \sprintf('Round %d repeats a meeting or a bye that another pairing avoids.', $round->number));
            }

            foreach ($round->pairings as $pairing) {
                if (null === $pairing->second) {
                    $byes[$pairing->first] = true;
                } else {
                    $met[$pairing->first][$pairing->second] = $met[$pairing->second][$pairing->first] = true;
                }
            }
        }

        if (2 * $rounds <= $players) {
            self::assertSame(0, $repeats, 'A field twice as large as its rounds always avoids a repeat.');
        }
    }

    #[DataProvider('swissFields')]
    public function testTheSameSeedPlaysTheSameTournament(int $players, int $rounds, int $draw): void
    {
        self::assertEquals($this->playSwiss($players, $rounds, $draw), $this->playSwiss($players, $rounds, $draw));
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function pools(): iterable
    {
        foreach ([[2, 1, 1], [3, 1, 2], [5, 1, 1], [6, 1, 2], [7, 2, 1], [9, 3, 2], [12, 2, 1], [17, 4, 2]] as [$players, $pools, $legs]) {
            yield \sprintf('%d players, %d pools, %d legs', $players, $pools, $legs) => [$players, $pools, $legs];
        }
    }

    #[DataProvider('pools')]
    public function testEveryPairOfAPoolMeetsOnceALegAndTheReturnSwapsTheSides(int $players, int $pools, int $legs): void
    {
        $service = new Tournaments(new InMemoryTournamentStore());
        $service->start(new Tournament('cup', 'seed-'.$players, $this->participants($players), [Stage::roundRobin('pools', $pools, $legs)]));

        $random = new Randomizer(new Mt19937($players));
        $first = [];
        $rests = [];
        $poolOf = [];

        while (!$service->get('cup')->isOver()) {
            $round = $service->prepareRound('cup');
            $service->publishRound('cup', $round);

            foreach ($round->pairings as $pairing) {
                $poolOf[$pairing->first] = $pairing->pool;

                if (null !== $pairing->second) {
                    $poolOf[$pairing->second] = $pairing->pool;
                }

                if (null === $pairing->second) {
                    self::assertTrue($pairing->rest);
                    $rests[$pairing->first] = ($rests[$pairing->first] ?? 0) + 1;

                    continue;
                }

                $first[$pairing->first][$pairing->second] = ($first[$pairing->first][$pairing->second] ?? 0) + 1;
                $service->record('cup', $round->key($pairing->table), $this->draw($random, true), 'org');
            }
        }

        $participants = $this->participants($players);

        foreach ($participants as $a) {
            foreach ($participants as $b) {
                if ($a >= $b || $poolOf[$a] !== $poolOf[$b]) {
                    continue;
                }

                $ab = $first[$a][$b] ?? 0;
                $ba = $first[$b][$a] ?? 0;
                self::assertSame($legs, $ab + $ba, \sprintf('%s and %s meet %d times.', $a, $b, $ab + $ba));

                if (2 === $legs) {
                    self::assertSame([1, 1], [$ab, $ba], \sprintf('%s and %s each play first once.', $a, $b));
                }
            }
        }

        $sizes = array_count_values(array_map(static fn (?string $pool): string => (string) $pool, $poolOf));

        foreach ($participants as $participant) {
            $odd = 1 === $sizes[(string) $poolOf[$participant]] % 2;
            self::assertSame($odd ? $legs : 0, $rests[$participant] ?? 0);
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function brackets(): iterable
    {
        foreach ([2, 3, 5, 8, 11, 16, 23] as $players) {
            yield \sprintf('%d players', $players) => [$players];
        }
    }

    #[DataProvider('brackets')]
    public function testAnEliminationEndsWithOneChampionAndByesOnlyInTheFirstRound(int $players): void
    {
        $service = new Tournaments(new InMemoryTournamentStore());
        $service->start(new Tournament('cup', 'seed', $this->participants($players), [Stage::singleElimination('cut')]));

        $random = new Randomizer(new Mt19937($players));
        $out = [];
        $events = [];

        while (!$service->get('cup')->isOver()) {
            $round = $service->prepareRound('cup');
            $service->publishRound('cup', $round);

            foreach ($round->pairings as $pairing) {
                self::assertArrayNotHasKey($pairing->first, $out);

                if (null === $pairing->second) {
                    self::assertSame(1, $round->number, 'A bye comes in the first round only.');

                    continue;
                }

                self::assertArrayNotHasKey($pairing->second, $out);
                $result = $this->draw($random, false);
                $out[$result->outcome->firstWins() ? $pairing->second : $pairing->first] = true;
                $events = [...$events, ...$service->record('cup', $round->key($pairing->table), $result, 'org')->events];
            }
        }

        self::assertCount($players - 1, $out);
        self::assertCount(1, $events);
        self::assertArrayNotHasKey($events[0]->winner, $out);
    }

    /**
     * @return list<Round>
     */
    private function playSwiss(int $players, int $rounds, int $draw): array
    {
        $service = new Tournaments(new InMemoryTournamentStore());
        $service->start(new Tournament('cup', \sprintf('seed-%d-%d', $players, $draw), $this->participants($players), [Stage::swiss('swiss', $rounds)]));

        $random = new Randomizer(new Mt19937($players * 100 + $draw));

        for ($number = 1; $number <= $rounds; ++$number) {
            $round = $service->prepareRound('cup');
            $service->publishRound('cup', $round);

            foreach ($round->pairings as $pairing) {
                if (null !== $pairing->second) {
                    $service->record('cup', $round->key($pairing->table), $this->draw($random, true), 'org');
                }
            }
        }

        return $service->get('cup')->rounds;
    }

    /**
     * Whether the participants can be paired without a second meeting or a second bye: a search over the pairs, the
     * first participant left paired with each other in turn.
     *
     * @param list<string>                       $left
     * @param array<string, array<string, true>> $met
     * @param array<string, true>                $byes
     */
    private static function avoidable(array $left, array $met, array $byes): bool
    {
        if ([] === $left) {
            return true;
        }

        if (1 === \count($left) % 2) {
            foreach ($left as $index => $participant) {
                if (!isset($byes[$participant])) {
                    $rest = $left;
                    unset($rest[$index]);

                    if (self::avoidable(array_values($rest), $met, $byes)) {
                        return true;
                    }
                }
            }

            return false;
        }

        $first = array_shift($left);

        foreach ($left as $index => $other) {
            if (!isset($met[$first][$other])) {
                $rest = $left;
                unset($rest[$index]);

                if (self::avoidable(array_values($rest), $met, $byes)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function draw(Randomizer $random, bool $draws): Result
    {
        return match ($random->getInt(0, $draws ? 2 : 1)) {
            0 => Result::firstWins(),
            1 => Result::secondWins(),
            default => Result::draw(),
        };
    }

    /**
     * @return list<string>
     */
    private function participants(int $count): array
    {
        return array_map(static fn (int $n): string => \sprintf('p%02d', $n), range(1, $count));
    }
}
