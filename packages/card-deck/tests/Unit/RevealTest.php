<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\CardDeck\Card;
use Tilttek\CardDeck\Deck;
use Tilttek\CardDeck\Exception\InvalidDefinition;
use Tilttek\CardDeck\Gesture\Shuffle;
use Tilttek\CardDeck\Reveal;
use Tilttek\Dice\Fairness\Seed;

#[CoversClass(Reveal::class)]
final class RevealTest extends TestCase
{
    public function testAPublishedGameGoesToAnArrayAndBack(): void
    {
        $reveal = new Reveal(new Deck(Card::of('a', 'X')), Seed::fromHex(str_repeat('01', 32)), 'c', [new Shuffle()]);
        $array = $reveal->toArray();

        self::assertSame([
            'deck' => [['id' => 'a', 'faces' => ['X'], 'back' => 'back']],
            'seed' => str_repeat('01', 32),
            'client_seed' => 'c',
            'gestures' => [['gesture' => 'shuffle', 'zone' => 'draw']],
        ], $array);
        self::assertEquals($reveal, Reveal::fromArray($array));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function badReveals(): iterable
    {
        $good = ['deck' => [['id' => 'a', 'faces' => ['X'], 'back' => 'b']], 'seed' => str_repeat('01', 32), 'client_seed' => '', 'gestures' => []];
        $shape = 'A published game is a deck, a seed, a client seed and at most 10000 gestures.';

        yield 'not an array' => ['x', $shape];
        yield 'no seed' => [array_diff_key($good, ['seed' => 0]), $shape];
        yield 'no client seed' => [array_diff_key($good, ['client_seed' => 0]), $shape];
        yield 'no gestures' => [array_diff_key($good, ['gestures' => 0]), $shape];
        yield 'gestures not a list' => [['gestures' => ['a' => []]] + $good, $shape];
        yield 'too many gestures' => [['gestures' => array_fill(0, 10_001, ['gesture' => 'shuffle', 'zone' => 'draw'])] + $good, $shape];
        yield 'bad seed' => [['seed' => 'zz'] + $good, 'A seed is 64 lower-case hexadecimal digits.'];
        yield 'no deck' => [array_diff_key($good, ['deck' => 0]), 'A deck is a list of cards.'];
        yield 'bad gesture' => [['gestures' => [[]]] + $good, 'A gesture is shuffle, draw, deal, discard, flip, turn_face, move or collect.'];
    }

    #[DataProvider('badReveals')]
    public function testAPublishedGameThatDoesNotDecodeIsRefused(mixed $data, string $message): void
    {
        $this->expectExceptionObject(InvalidDefinition::because($message));

        Reveal::fromArray($data);
    }

    public function testTenThousandGesturesAreAccepted(): void
    {
        $data = ['deck' => [['id' => 'a', 'faces' => ['X'], 'back' => 'b']], 'seed' => str_repeat('01', 32), 'client_seed' => '', 'gestures' => array_fill(0, 10_000, ['gesture' => 'shuffle', 'zone' => 'draw'])];

        self::assertCount(10_000, Reveal::fromArray($data)->gestures);
    }
}
