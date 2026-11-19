<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Raffle\RaffleProof;
use Tilttek\Raffle\Raffles;
use Tilttek\Raffle\RaffleVerifier;
use Tilttek\Raffle\Stake;
use Tilttek\Raffle\Store\InMemoryRaffleStore;
use Tilttek\Raffle\Tests\Unit\Fixture\FakeBeacon;
use Tilttek\Raffle\Tests\Unit\Fixture\FrozenClock;

/**
 * A draw of several prizes — a podium and its alternates — keeps one proof: the order drawn is the order of the
 * prizes, and a player who changes it, or drops a name, breaks the proof.
 */
#[CoversClass(RaffleProof::class)]
#[CoversClass(RaffleVerifier::class)]
final class PodiumTest extends TestCase
{
    public function testAPodiumWithAlternatesHoldsAndGivesEachPlace(): void
    {
        $proof = $this->podium();
        $json = $proof->toJson();

        self::assertCount(3, $proof->winnerList());
        self::assertCount(2, $proof->alternateList());
        self::assertSame([], new RaffleVerifier()->problemsOfJson($json));
        self::assertSame($proof->drawn, RaffleProof::fromJson($json)->drawn);

        foreach ($proof->drawn as $index => $entrant) {
            self::assertSame($index + 1, $proof->place($entrant));
        }

        $undrawn = array_values(array_diff($proof->entrants, $proof->drawn));
        self::assertNotSame([], $undrawn);
        self::assertNull($proof->place($undrawn[0]));
    }

    public function testThePlacesOfThePodiumArePartOfTheProof(): void
    {
        $proof = $this->podium();
        $swapped = $proof->drawn;
        [$swapped[0], $swapped[1]] = [$swapped[1], $swapped[0]];
        $dropped = \array_slice($proof->drawn, 0, 4);

        foreach ([$swapped, $dropped] as $drawn) {
            $changed = RaffleProof::fromArray([...$proof->toArray(), 'drawn' => $drawn]);

            self::assertFalse(new RaffleVerifier()->holds($changed));
        }
    }

    private function podium(): RaffleProof
    {
        $clock = new FrozenClock();
        $raffles = new Raffles(new InMemoryRaffleStore(), new FakeBeacon(), clock: $clock, secure: new Randomizer(new Xoshiro256StarStar(9)));
        $raffles->open('podium', Stake::Prize, winners: 3, alternates: 2);

        foreach (range(1, 12) as $entrant) {
            $raffles->enter('podium', 'member-'.$entrant);
        }

        $raffles->seal('podium');
        $clock->move('+1 minute');

        return $raffles->draw('podium');
    }
}
