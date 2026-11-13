<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\WargameArmy\ArrayInput;
use Tilttek\WargameArmy\Exception\InvalidArmyList;
use Tilttek\WargameArmy\Exception\InvalidCatalogue;
use Tilttek\WargameArmy\Fault;

#[CoversClass(ArrayInput::class)]
#[CoversClass(Fault::class)]
#[CoversClass(InvalidCatalogue::class)]
#[CoversClass(InvalidArmyList::class)]
final class ArrayInputTest extends TestCase
{
    public function testAListOfExactlyTheMostEntriesIsGivenBackWhole(): void
    {
        $input = new ArrayInput(Fault::Catalogue);

        self::assertSame(['a', 2, null], $input->list(['k' => ['a', 2, null]], 'k', 'unit', 3));
        self::assertSame([], $input->list([], 'k', 'unit', 0));
    }

    public function testAListOfOneMoreThanTheMostIsRefused(): void
    {
        $this->expectException(InvalidArmyList::class);
        $this->expectExceptionMessageIsOrContains('A detachment has at most 3 "k".');

        new ArrayInput(Fault::ArmyList)->list(['k' => [1, 2, 3, 4]], 'k', 'detachment', 3);
    }

    public function testStringsAreAllGivenBack(): void
    {
        self::assertSame(['a', 'b', 'c'], new ArrayInput(Fault::Catalogue)->strings(['k' => ['a', 'b', 'c']], 'k', 'unit', 3));
    }

    public function testCountsOfExactlyTheMostEntriesAreGivenBackWhole(): void
    {
        $input = new ArrayInput(Fault::Catalogue);

        self::assertSame(['a' => 1, 'b' => 2], $input->counts(['k' => ['a' => 1, 'b' => 2]], 'k', 'unit', 2));
        self::assertSame([], $input->counts([], 'k', 'unit', 0));
    }

    public function testCountsOfOneMoreThanTheMostAreRefused(): void
    {
        $this->expectException(InvalidCatalogue::class);
        $this->expectExceptionMessageIsOrContains('An option has at most 2 "k".');

        new ArrayInput(Fault::Catalogue)->counts(['k' => ['a' => 1, 'b' => 2, 'c' => 3]], 'k', 'option', 2);
    }
}
