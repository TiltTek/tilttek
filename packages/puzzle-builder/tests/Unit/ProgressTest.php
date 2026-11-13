<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\PuzzleBuilder\Exception\InvalidProgress;
use Tilttek\PuzzleBuilder\Instant;
use Tilttek\PuzzleBuilder\Progress;
use Tilttek\PuzzleBuilder\Record\HintTaken;
use Tilttek\PuzzleBuilder\Record\Solved;
use Tilttek\PuzzleBuilder\Tests\Unit\Fixture\Manor;

#[CoversClass(Progress::class)]
#[CoversClass(Instant::class)]
#[CoversClass(Solved::class)]
#[CoversClass(HintTaken::class)]
#[CoversClass(InvalidProgress::class)]
final class ProgressTest extends TestCase
{
    public function testAProgressStartsEmpty(): void
    {
        $progress = Progress::start();

        self::assertSame([], $progress->solved());
        self::assertSame([], $progress->hints());
        self::assertFalse($progress->isSolved('clock'));
        self::assertFalse($progress->isOpened('letter'));
        self::assertSame(0, $progress->guesses('clock'));
        self::assertSame(['solved' => [], 'opened' => [], 'hints' => [], 'guesses' => []], $progress->toArray());
    }

    public function testEachMoveGivesANewProgress(): void
    {
        $at = new DateTimeImmutable('2026-11-13 15:00:00.25', new DateTimeZone('America/Toronto'));
        $start = Progress::start();
        $progress = $start
            ->withGuess('clock')
            ->withGuess('clock')
            ->withHint(new HintTaken('clock', 1, $at))
            ->withHint(new HintTaken('vault', 1, $at, true))
            ->withSolved(new Solved('clock', $at))
            ->withSolved(new Solved('painting', $at, true))
            ->withOpened('letter', $at);

        self::assertSame([], $start->solved());
        self::assertTrue($progress->isSolved('clock'));
        self::assertTrue($progress->isOpened('letter'));
        self::assertFalse($progress->isOpened('clock'));
        self::assertSame(2, $progress->guesses('clock'));
        self::assertSame(['clock', 'painting'], array_keys($progress->solved()));
        self::assertCount(1, $progress->hintsOf('vault'));
        self::assertSame([0], array_keys($progress->hintsOf('vault')));
        self::assertSame('vault', $progress->hints()[1]->puzzle);
        self::assertSame([
            'solved' => [
                'clock' => ['at' => '2026-11-13T20:00:00.250000Z', 'skipped' => false],
                'painting' => ['at' => '2026-11-13T20:00:00.250000Z', 'skipped' => true],
            ],
            'opened' => ['letter' => '2026-11-13T20:00:00.250000Z'],
            'hints' => [
                ['puzzle' => 'clock', 'tier' => 1, 'at' => '2026-11-13T20:00:00.250000Z', 'pushed' => false],
                ['puzzle' => 'vault', 'tier' => 1, 'at' => '2026-11-13T20:00:00.250000Z', 'pushed' => true],
            ],
            'guesses' => ['clock' => 2],
        ], $progress->toArray());
        self::assertSame('UTC', $progress->solved()['clock']->at->getTimezone()->getName());
    }

    public function testAProgressReadsBackAsItWasWritten(): void
    {
        $values = [
            'solved' => ['clock' => ['at' => '2026-11-13T20:00:00.250000Z', 'skipped' => false]],
            'opened' => ['letter' => '2026-11-13T20:01:00.000000Z'],
            'hints' => [
                ['puzzle' => 'clock', 'tier' => 1, 'at' => '2026-11-13T19:59:00.000000Z', 'pushed' => false],
                ['puzzle' => 'clock', 'tier' => 2, 'at' => '2026-11-13T19:59:30.000000Z', 'pushed' => true],
            ],
            'guesses' => ['clock' => 3, 'painting' => 1_000_000],
        ];

        self::assertSame($values, Progress::fromArray(Manor::scenario(), $values)->toArray());
        self::assertSame([], Progress::fromArray(Manor::scenario(), [])->toArray()['solved']);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function badValues(): iterable
    {
        $at = '2026-11-13T20:00:00.000000Z';

        yield 'an unknown field' => [['scores' => []], 'The field "scores" is not one of a progress.'];
        yield 'solved a list' => [['solved' => [1]], 'The field "solved" of a progress is an object by puzzle.'];
        yield 'solved not an array' => [['solved' => 'clock'], 'The field "solved" of a progress is an object by puzzle.'];
        yield 'a solve not an array' => [['solved' => ['clock' => $at]], 'A puzzle solved holds its time and whether it was skipped.'];
        yield 'a solve without its flag' => [['solved' => ['clock' => ['at' => $at]]], 'A puzzle solved holds its time and whether it was skipped.'];
        yield 'a solve with more' => [['solved' => ['clock' => ['at' => $at, 'skipped' => false, 'by' => 1]]], 'A puzzle solved holds its time and whether it was skipped.'];
        yield 'a solve of an unknown puzzle' => [['solved' => ['door' => ['at' => $at, 'skipped' => false]]], 'A progress names the puzzles of its scenario only.'];
        yield 'a solve at a bad time' => [['solved' => ['clock' => ['at' => '2026-11-13 20:00', 'skipped' => false]]], 'A time is written in UTC to the microsecond, as 2026-11-13T20:15:03.250000Z.'];
        yield 'a solve whose time is not a text' => [['solved' => ['clock' => ['time' => $at, 'skipped' => false]]], 'A time is written in UTC'];
        yield 'opened a puzzle that opens by itself' => [['opened' => ['clock' => $at]], 'A progress names the puzzles the host opens only as opened.'];
        yield 'opened an unknown puzzle' => [['opened' => ['door' => $at]], 'A progress names the puzzles of its scenario only.'];
        yield 'opened at a time out of range' => [['opened' => ['letter' => '2026-02-30T20:00:00.000000Z']], 'A time is written in UTC'];
        yield 'hints not a list' => [['hints' => ['a' => 1]], 'The hints revealed are a list.'];
        yield 'hints not an array' => [['hints' => 'clock'], 'The hints revealed are a list.'];
        yield 'a hint not an array' => [['hints' => ['clock']], 'A hint revealed holds its puzzle, its tier, its time and whether it was pushed.'];
        yield 'a hint with a missing field' => [['hints' => [['puzzle' => 'clock', 'tier' => 1, 'at' => $at]]], 'A hint revealed holds its puzzle, its tier'];
        yield 'a hint with a tier not a number' => [['hints' => [['puzzle' => 'clock', 'tier' => '1', 'at' => $at, 'pushed' => false]]], 'A hint revealed holds its puzzle, its tier'];
        yield 'a hint not pushed nor asked' => [['hints' => [['puzzle' => 'clock', 'tier' => 1, 'at' => $at, 'pushed' => 0]]], 'A hint revealed holds its puzzle, its tier'];
        yield 'a hint of an unknown puzzle' => [['hints' => [['puzzle' => 'door', 'tier' => 1, 'at' => $at, 'pushed' => false]]], 'A progress names the puzzles of its scenario only.'];
        yield 'a hint of a puzzle not a text' => [['hints' => [['puzzle' => 1, 'tier' => 1, 'at' => $at, 'pushed' => false]]], 'A progress names the puzzles of its scenario only.'];
        yield 'a hint that skips a tier' => [['hints' => [['puzzle' => 'clock', 'tier' => 2, 'at' => $at, 'pushed' => false]]], 'The hints of the puzzle "clock" are revealed tier after tier, up to its last.'];
        yield 'a hint past the last' => [['hints' => [['puzzle' => 'painting', 'tier' => 1, 'at' => $at, 'pushed' => false]]], 'The hints of the puzzle "painting" are revealed tier after tier, up to its last.'];
        yield 'a hint at a bad time' => [['hints' => [['puzzle' => 'clock', 'tier' => 1, 'at' => 'now', 'pushed' => false]]], 'A time is written in UTC'];
        yield 'guesses a list' => [['guesses' => [3]], 'The field "guesses" of a progress is an object by puzzle.'];
        yield 'no guess' => [['guesses' => ['clock' => 0]], 'The guesses of a puzzle are 1 to 1000000.'];
        yield 'too many guesses' => [['guesses' => ['clock' => 1_000_001]], 'The guesses of a puzzle are 1 to 1000000.'];
        yield 'guesses not a number' => [['guesses' => ['clock' => '3']], 'The guesses of a puzzle are 1 to 1000000.'];
        yield 'guesses of an unknown puzzle' => [['guesses' => ['door' => 3]], 'A progress names the puzzles of its scenario only.'];
    }

    /**
     * @param array<array-key, mixed> $values
     */
    #[DataProvider('badValues')]
    public function testValuesThatAreNotAProgressAreRefused(array $values, string $message): void
    {
        $this->expectException(InvalidProgress::class);
        $this->expectExceptionMessageIsOrContains($message);

        Progress::fromArray(Manor::scenario(), $values);
    }

    public function testALongUnknownFieldIsCut(): void
    {
        $this->expectException(InvalidProgress::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The field "%s" is not one of a progress.', str_repeat('x', 64)));

        Progress::fromArray(Manor::scenario(), [str_repeat('x', 65) => []]);
    }

    public function testAMultibyteUnknownFieldIsCutByCharacters(): void
    {
        $this->expectException(InvalidProgress::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The field "%s" is not one of a progress.', str_repeat('é', 64)));

        Progress::fromArray(Manor::scenario(), [str_repeat('é', 65) => []]);
    }

    public function testASolveWhoseFlagIsNotABooleanIsRefused(): void
    {
        $this->expectException(InvalidProgress::class);
        $this->expectExceptionMessageIsOrContains('A puzzle solved holds its time and whether it was skipped.');

        Progress::fromArray(Manor::scenario(), ['solved' => ['clock' => ['at' => '2026-11-13T20:00:00.000000Z', 'skipped' => 'no']]]);
    }

    public function testOneGuessIsTaken(): void
    {
        self::assertSame(1, Progress::fromArray(Manor::scenario(), ['guesses' => ['clock' => 1]])->guesses('clock'));
    }

    public function testTheHintsOfAPuzzleAreThoseOfThatPuzzle(): void
    {
        $at = new DateTimeImmutable('2026-11-13 20:00 UTC');
        $progress = Progress::start()->withHint(new HintTaken('clock', 1, $at))->withHint(new HintTaken('vault', 1, $at))->withHint(new HintTaken('clock', 2, $at));

        self::assertSame([1, 2], array_map(static fn (HintTaken $hint): int => $hint->tier, $progress->hintsOf('clock')));
        self::assertSame(['vault'], array_map(static fn (HintTaken $hint): string => $hint->puzzle, $progress->hintsOf('vault')));
        self::assertSame([], $progress->hintsOf('painting'));
    }

    public function testEachMoveKeepsWhatCameBefore(): void
    {
        $at = new DateTimeImmutable('2026-11-13 20:00 UTC');
        $progress = Progress::start()->withOpened('letter', $at)->withOpened('other', $at)->withGuess('clock')->withGuess('painting');

        self::assertTrue($progress->isOpened('letter'));
        self::assertTrue($progress->isOpened('other'));
        self::assertSame(1, $progress->guesses('clock'));
        self::assertSame(1, $progress->guesses('painting'));
    }

    public function testTheGuessesStopAtTheirBound(): void
    {
        $progress = Progress::fromArray(Manor::scenario(), ['guesses' => ['clock' => 1_000_000]])->withGuess('clock');

        self::assertSame(1_000_000, $progress->guesses('clock'));
    }
}
