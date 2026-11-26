<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Tests\Unit\Seeding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Tournament\SeededDraw;
use Tilttek\Tournament\Seeding\FixedSeeding;
use Tilttek\Tournament\Seeding\InitialOrder;

#[CoversClass(FixedSeeding::class)]
#[CoversClass(InitialOrder::class)]
final class SeedingTest extends TestCase
{
    public function testFixedStrengthsAreGivenForTheParticipantsAskedFor(): void
    {
        $seeding = new FixedSeeding(['a' => 1800, 'b' => 1500, 'c' => 1650]);

        self::assertSame(['a' => 1800, 'c' => 1650], $seeding->strengths(['a', 'c', 'd']));
    }

    public function testSeedsInOrderAreStrengthsFromTheFirst(): void
    {
        self::assertSame(['a' => 3, 'b' => 2, 'c' => 1], FixedSeeding::inOrder(['a', 'b', 'c'])->strengths(['a', 'b', 'c']));
    }

    public function testTheInitialOrderIsTheStrongestFirstThenTheSeed(): void
    {
        $participants = ['u1', 'u2', 'a', 'b', 'c', 'u3'];
        $order = InitialOrder::of($participants, 'seed', new FixedSeeding(['a' => 1500, 'b' => 1800, 'c' => 1500]));
        $unknown = SeededDraw::order('seed', ['u1', 'u2', 'u3']);
        $tied = SeededDraw::order('seed', ['a', 'c']);

        self::assertSame(['b', ...$tied, ...$unknown], $order);
    }

    public function testANegativeStrengthStaysBeforeTheUnknown(): void
    {
        self::assertSame(['a', 'b'], InitialOrder::of(['b', 'a'], 'seed', new FixedSeeding(['a' => -5])));
        self::assertSame(['a', 'b'], InitialOrder::of(['a', 'b'], 'seed', new FixedSeeding(['a' => 0])));
    }

    public function testWithoutASeedingTheSeedOrdersEveryone(): void
    {
        self::assertSame(SeededDraw::order('x', ['a', 'b', 'c', 'd']), InitialOrder::of(['a', 'b', 'c', 'd'], 'x'));
    }
}
