<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Tests\Unit\RoundRobin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Tournament\Exception\InvalidTournament;
use Tilttek\Tournament\Pairing;
use Tilttek\Tournament\PairingReason;
use Tilttek\Tournament\ReasonKind;
use Tilttek\Tournament\RoundRobin\BergerTable;
use Tilttek\Tournament\RoundRobin\Pools;

#[CoversClass(BergerTable::class)]
#[CoversClass(Pools::class)]
final class RoundRobinTest extends TestCase
{
    public function testTheBergerTableOfFour(): void
    {
        $rounds = BergerTable::rounds(['p1', 'p2', 'p3', 'p4']);

        self::assertSame([
            [['p1', 'p4'], ['p3', 'p2']],
            [['p4', 'p2'], ['p1', 'p3']],
            [['p3', 'p4'], ['p2', 'p1']],
        ], $this->seats($rounds));
        self::assertEquals([
            new PairingReason(ReasonKind::Scheduled, 'p1', 'p4'),
            new PairingReason(ReasonKind::Scheduled, 'p4', 'p1'),
        ], $rounds[0][0]->reasons);
        self::assertSame([1, 2], array_map(static fn (Pairing $pairing): int => $pairing->table, $rounds[0]));
        self::assertNull($rounds[0][0]->pool);
    }

    public function testAnOddPoolRestsOneEachRound(): void
    {
        $rounds = BergerTable::rounds(['p1', 'p2', 'p3'], pool: 'A');

        self::assertSame([
            [['p3', 'p2'], ['p1', null]],
            [['p1', 'p3'], ['p2', null]],
            [['p2', 'p1'], ['p3', null]],
        ], $this->seats($rounds));
        self::assertTrue($rounds[0][1]->rest);
        self::assertFalse($rounds[0][0]->rest);
        self::assertSame(2, $rounds[0][1]->table);
        self::assertSame('A', $rounds[0][1]->pool);
        self::assertSame('A', $rounds[0][0]->pool);
        self::assertEquals([new PairingReason(ReasonKind::Rest, 'p1')], $rounds[0][1]->reasons);
    }

    public function testTheReturnLegSwapsTheSides(): void
    {
        $rounds = BergerTable::rounds(['p1', 'p2', 'p3', 'p4'], 2);

        self::assertCount(6, $rounds);
        self::assertSame([['p4', 'p1'], ['p2', 'p3']], $this->seats([$rounds[3]])[0]);
        self::assertEquals([
            new PairingReason(ReasonKind::ReturnMatch, 'p4', 'p1'),
            new PairingReason(ReasonKind::ReturnMatch, 'p1', 'p4'),
        ], $rounds[3][0]->reasons);

        $odd = BergerTable::rounds(['p1', 'p2', 'p3'], 2);
        self::assertSame([['p2', 'p3'], ['p1', null]], $this->seats([$odd[3]])[0]);
    }

    public function testTheSidesAlternateDownTheTableOfEight(): void
    {
        $rounds = BergerTable::rounds(['p1', 'p2', 'p3', 'p4', 'p5', 'p6', 'p7', 'p8']);

        self::assertSame([['p1', 'p8'], ['p7', 'p2'], ['p3', 'p6'], ['p5', 'p4']], $this->seats($rounds)[0]);
        self::assertSame([['p8', 'p2'], ['p1', 'p3'], ['p4', 'p7'], ['p6', 'p5']], $this->seats($rounds)[1]);
        self::assertSame([['p3', 'p8'], ['p2', 'p4'], ['p5', 'p1'], ['p7', 'p6']], $this->seats($rounds)[2]);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function sizes(): iterable
    {
        foreach ([2, 3, 4, 5, 6, 7, 8, 9, 10, 16, 17, 64] as $size) {
            foreach ([1, 2] as $legs) {
                yield $size.' in '.$legs => [$size, $legs];
            }
        }
    }

    #[DataProvider('sizes')]
    public function testEveryPairMeetsOnceALegAndTheSidesAreEven(int $size, int $legs): void
    {
        $participants = array_map(static fn (int $n): string => 'p'.$n, range(1, $size));
        $rounds = BergerTable::rounds($participants, $legs);
        $met = [];
        $firsts = array_fill_keys($participants, 0);
        $rests = array_fill_keys($participants, 0);

        self::assertCount(($size - 1 + $size % 2) * $legs, $rounds);

        foreach ($rounds as $tables) {
            $seated = [];

            foreach ($tables as $pairing) {
                $seated[] = $pairing->first;

                if (null === $pairing->second) {
                    ++$rests[$pairing->first];

                    continue;
                }

                $seated[] = $pairing->second;
                ++$firsts[$pairing->first];
                $key = min($pairing->first, $pairing->second).'|'.max($pairing->first, $pairing->second);
                $met[$key] = ($met[$key] ?? 0) + 1;
            }

            sort($seated);
            $expected = $participants;
            sort($expected);
            self::assertSame($expected, $seated, 'Each round seats everyone once.');
        }

        self::assertCount(intdiv($size * ($size - 1), 2), $met);
        self::assertSame([$legs], array_values(array_unique($met)));
        self::assertLessThanOrEqual(1 === $legs ? 1 : 0, max($firsts) - min($firsts));
        self::assertSame([$size % 2 * $legs], array_values(array_unique($rests)));
    }

    public function testAPoolHasTwoParticipantsAtLeast(): void
    {
        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('A pool has 2 to 64 participants.');
        BergerTable::rounds(['p1']);
    }

    public function testAPoolHasSixtyFourParticipantsAtMost(): void
    {
        $this->expectException(InvalidTournament::class);
        BergerTable::rounds(array_map(static fn (int $n): string => 'p'.$n, range(1, 65)));
    }

    public function testAPoolIsPlayedInOneOrTwoLegs(): void
    {
        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('A pool is played in 1 or 2 legs.');
        BergerTable::rounds(['p1', 'p2'], 3);
    }

    public function testAPoolIsPlayedInOneLegAtLeast(): void
    {
        $this->expectException(InvalidTournament::class);
        BergerTable::rounds(['p1', 'p2'], 0);
    }

    public function testThePoolsAreFilledInASnake(): void
    {
        $pools = Pools::snake(['s1', 's2', 's3', 's4', 's5', 's6', 's7'], 3);

        self::assertSame(['A' => ['s1', 's6', 's7'], 'B' => ['s2', 's5'], 'C' => ['s3', 's4']], $pools);
        self::assertSame(['A' => ['s1', 's2']], Pools::snake(['s1', 's2'], 1));
        self::assertSame(['A', 'B'], array_keys(Pools::snake(['s1', 's2', 's3', 's4'], 2)));
    }

    public function testTwentySixPoolsAtMost(): void
    {
        $pools = Pools::snake(array_map(static fn (int $n): string => 'p'.$n, range(1, 52)), 26);
        self::assertSame('Z', array_key_last($pools));

        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('A stage has 1 to 26 pools.');
        Pools::snake(array_map(static fn (int $n): string => 'p'.$n, range(1, 54)), 27);
    }

    public function testOnePoolAtLeast(): void
    {
        $this->expectException(InvalidTournament::class);
        Pools::snake(['p1', 'p2'], 0);
    }

    public function testEachPoolHasTwoParticipants(): void
    {
        self::assertCount(2, Pools::snake(['p1', 'p2', 'p3', 'p4'], 2));
        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('Each pool has 2 participants at least.');
        Pools::snake(['p1', 'p2', 'p3'], 2);
    }

    /**
     * @param list<list<Pairing>> $rounds
     *
     * @return list<list<array{string, ?string}>>
     */
    private function seats(array $rounds): array
    {
        return array_map(static fn (array $tables): array => array_map(static fn (Pairing $pairing): array => [$pairing->first, $pairing->second], $tables), $rounds);
    }
}
