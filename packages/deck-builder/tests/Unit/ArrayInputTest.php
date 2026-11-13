<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\DeckBuilder\ArrayInput;
use Tilttek\DeckBuilder\Exception\InvalidDeck;

#[CoversClass(ArrayInput::class)]
#[CoversClass(InvalidDeck::class)]
final class ArrayInputTest extends TestCase
{
    public function testAListOfExactlyTheMostEntriesIsGivenBackWhole(): void
    {
        self::assertSame(['a', 2, null], ArrayInput::list(['k' => ['a', 2, null]], 'k', 'format', 3));
        self::assertSame([], ArrayInput::list([], 'k', 'format', 0));
    }

    public function testAListOfOneMoreThanTheMostIsRefused(): void
    {
        $this->expectException(InvalidDeck::class);
        $this->expectExceptionMessageIsOrContains('A format has at most 3 "k".');

        ArrayInput::list(['k' => [1, 2, 3, 4]], 'k', 'format', 3);
    }

    public function testStringsAreAllGivenBack(): void
    {
        self::assertSame(['a', 'b', 'c'], ArrayInput::strings(['k' => ['a', 'b', 'c']], 'k', 'format', 3));
    }

    public function testCountsOfExactlyTheMostEntriesAreGivenBackWhole(): void
    {
        self::assertSame(['a' => 1, 'b' => 2], ArrayInput::counts(['k' => ['a' => 1, 'b' => 2]], 'k', 'format', 2));
        self::assertSame([], ArrayInput::counts([], 'k', 'format', 0));
    }

    public function testCountsOfOneMoreThanTheMostAreRefused(): void
    {
        $this->expectException(InvalidDeck::class);
        $this->expectExceptionMessageIsOrContains('A format has at most 2 "k".');

        ArrayInput::counts(['k' => ['a' => 1, 'b' => 2, 'c' => 3]], 'k', 'format', 2);
    }
}
