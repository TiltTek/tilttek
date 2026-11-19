<?php

declare(strict_types=1);

namespace Tilttek\Rating\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Rating\Exception\InvalidRating;
use Tilttek\Rating\GameResult;
use Tilttek\Rating\Identifier;

#[CoversClass(GameResult::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(InvalidRating::class)]
final class GameResultTest extends TestCase
{
    public function testADuelIsTwoSidesOfOnePlayer(): void
    {
        $win = GameResult::duel('r1', 'chess', self::at(), 'ann', 'bob');
        $draw = GameResult::duel('r2', 'chess', self::at(), 'ann', 'bob', draw: true, friendly: true);

        self::assertSame([['ann'], ['bob']], $win->sides);
        self::assertSame([1, 2], $win->ranks);
        self::assertFalse($win->friendly);
        self::assertSame([1, 1], $draw->ranks);
        self::assertTrue($draw->friendly);
        self::assertSame(['ann', 'bob'], $win->members());
    }

    public function testAResultIsRatedUnlessItIsSaidFriendly(): void
    {
        self::assertFalse(new GameResult('r1', 'chess', self::at(), [['ann'], ['bob']], [1, 2])->friendly);
        self::assertFalse(GameResult::placings('r1', 'chess', self::at(), [['ann'], ['bob']])->friendly);
    }

    public function testPlacingsGiveOneSidePerPlayer(): void
    {
        $result = GameResult::placings('r1', 'chess', self::at(), ['x' => ['ann'], 'y' => ['b' => 'bob', 'c' => 'cy'], 'z' => ['dee']], friendly: true);

        self::assertSame([['ann'], ['bob'], ['cy'], ['dee']], $result->sides);
        self::assertSame([1, 2, 2, 3], $result->ranks);
        self::assertTrue($result->friendly);
    }

    public function testSidesAndRanksAreReadAsLists(): void
    {
        $result = new GameResult('r1', 'chess', self::at(), [5 => ['x' => 'ann', 'y' => 'bob'], 9 => ['cy']], [3 => 2, 7 => 1]);

        self::assertSame([['ann', 'bob'], ['cy']], $result->sides);
        self::assertSame([2, 1], $result->ranks);
    }

    public function testAResultIsWrittenAndReadBack(): void
    {
        $result = new GameResult('r1', 'chess', new DateTimeImmutable('2026-11-13 15:00:00.25 -05:00'), [['ann', 'bob'], ['cy']], [2, 1], true);
        $data = $result->toArray();

        self::assertSame(['id' => 'r1', 'game' => 'chess', 'played_at' => '2026-11-13T20:00:00.250000Z', 'sides' => [['ann', 'bob'], ['cy']], 'ranks' => [2, 1], 'friendly' => true], $data);
        self::assertEquals($result->toArray(), GameResult::fromArray($data)->toArray());
        self::assertFalse(GameResult::fromArray([...$data, 'friendly' => null])->friendly);
        self::assertSame('2026-11-13T20:00:00.000000Z', GameResult::fromArray([...$data, 'played_at' => '2026-11-13T20:00:00Z'])->toArray()['played_at']);
        self::assertSame('2026-11-13T20:00:00.000000Z', GameResult::fromArray([...$data, 'played_at' => '2026-11-13T22:00:00+02:00'])->toArray()['played_at']);
        self::assertSame('2028-02-29T23:59:59.000000Z', GameResult::fromArray([...$data, 'played_at' => '2028-02-29T23:59:59Z'])->toArray()['played_at']);
    }

    public function testWhatDoesNotReadAsAResultIsRefused(): void
    {
        $data = GameResult::duel('r1', 'chess', self::at(), 'ann', 'bob')->toArray();
        $shape = 'A result has an id, a game, a played_at time, sides and ranks, and may be friendly.';
        $time = 'The time a game was played is an ISO 8601 date and time with its offset.';
        $bad = [
            [[...$data, 'id' => 1], $shape],
            [[...$data, 'game' => 1], $shape],
            [[...$data, 'played_at' => 1], $shape],
            [[...$data, 'sides' => 'ann'], $shape],
            [[...$data, 'ranks' => 1], $shape],
            [[...$data, 'friendly' => 'yes'], $shape],
            [[...$data, 'sides' => ['ann', ['bob']]], 'A side is a list of member identifiers.'],
            [[...$data, 'sides' => [['ann', 2], ['bob']]], 'A side is a list of member identifiers.'],
            [[...$data, 'ranks' => [1, '2']], 'A rank is a whole number.'],
            [[...$data, 'played_at' => '2026-11-13 20:00:00'], $time],
            [[...$data, 'played_at' => 'x2026-11-13T20:00:00Z'], $time],
            [[...$data, 'played_at' => '2026-11-13T20:00:00Zx'], $time],
            [[...$data, 'played_at' => '2026-11-13T20:00:00Z +1 day'], $time],
            [[...$data, 'played_at' => "2026-11-13T20:00:00Z\n"], $time],
            [[...$data, 'played_at' => '2026-13-45T20:00:00Z'], $time],
            [[...$data, 'played_at' => '2026-02-30T20:00:00Z'], $time],
            [[...$data, 'played_at' => '2026-11-13T24:00:00Z'], $time],
            [[...$data, 'played_at' => '2026-11-13T20:60:00+01:00'], $time],
        ];
        $missing = $data;
        unset($missing['id']);
        $bad[] = [$missing, $shape];

        foreach ($bad as [$values, $message]) {
            try {
                GameResult::fromArray($values);
                self::fail('Values were read as a result: '.$message);
            } catch (InvalidRating $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }
    }

    public function testTheShapeOfAResultIsChecked(): void
    {
        $tooManySides = array_map(static fn (int $player): array => ['p'.$player], range(1, 65));
        $tooManyPlayers = array_map(static fn (int $side): array => array_map(static fn (int $player): string => 's'.$side.'p'.$player, range(1, 32)), range(1, 9));
        $bad = [
            'The identifier of a result is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter or a digit.' => static fn (): GameResult => GameResult::duel('-r', 'chess', self::at(), 'ann', 'bob'),
            'The identifier of a game is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter or a digit.' => static fn (): GameResult => GameResult::duel('r1', 'chess game', self::at(), 'ann', 'bob'),
            'The identifier of a member is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter or a digit.' => static fn (): GameResult => GameResult::duel('r1', 'chess', self::at(), 'ann', str_repeat('b', 129)),
            'A result has 2 to 64 sides.' => static fn (): GameResult => new GameResult('r1', 'chess', self::at(), [['ann']], [1]),
            'A result has at most 256 players.' => static fn (): GameResult => new GameResult('r1', 'chess', self::at(), $tooManyPlayers, array_fill(0, 9, 1)),
            'A result gives one rank to each of its sides.' => static fn (): GameResult => new GameResult('r1', 'chess', self::at(), [['ann'], ['bob']], [1]),
            'A side has 1 to 32 players.' => static fn (): GameResult => new GameResult('r1', 'chess', self::at(), [['ann'], []], [1, 2]),
            'The member ann plays on one side only, once.' => static fn (): GameResult => new GameResult('r1', 'chess', self::at(), [['ann'], ['bob', 'ann']], [1, 2]),
            'A rank is 1 to the 2 sides of the result.' => static fn (): GameResult => new GameResult('r1', 'chess', self::at(), [['ann'], ['bob']], [0, 2]),
        ];
        $bad['A result has 2 to 64 sides. '] = static fn (): GameResult => new GameResult('r1', 'chess', self::at(), $tooManySides, array_fill(0, 65, 1));
        $bad['A side has 1 to 32 players. '] = static fn (): GameResult => new GameResult('r1', 'chess', self::at(), [['ann'], array_map(static fn (int $player): string => 'p'.$player, range(1, 33))], [1, 2]);
        $bad['A rank is 1 to the 2 sides of the result. '] = static fn (): GameResult => new GameResult('r1', 'chess', self::at(), [['ann'], ['bob']], [1, 3]);

        foreach ($bad as $message => $build) {
            try {
                $build();
                self::fail('A result was taken: '.$message);
            } catch (InvalidRating $invalid) {
                self::assertSame(trim($message), $invalid->getMessage());
            }
        }

        $largest = array_map(static fn (int $side): array => array_map(static fn (int $player): string => 's'.$side.'p'.$player, range(1, 32)), range(1, 8));
        self::assertCount(256, new GameResult('r1', 'chess', self::at(), $largest, range(1, 8))->members());
        self::assertCount(64, new GameResult('r1', 'chess', self::at(), \array_slice($tooManySides, 0, 64), array_fill(0, 64, 64))->sides);
        self::assertSame(str_repeat('a', 128), GameResult::duel(str_repeat('a', 128), 'G', self::at(), '1', 'b:c@d.e_f-g')->id);
    }

    private static function at(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-13 20:00:00 UTC');
    }
}
