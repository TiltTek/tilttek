<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\PuzzleBuilder\Answer\AnswerKey;
use Tilttek\PuzzleBuilder\Exception\InvalidScenario;
use Tilttek\PuzzleBuilder\Hint;
use Tilttek\PuzzleBuilder\Puzzle;
use Tilttek\PuzzleBuilder\Unlock;

#[CoversClass(Puzzle::class)]
#[CoversClass(Hint::class)]
#[CoversClass(InvalidScenario::class)]
final class PuzzleTest extends TestCase
{
    public function testAPuzzleKeepsWhatItIsGiven(): void
    {
        $key = new AnswerKey(['midnight']);
        $hints = [new Hint('Look at the hands', 60), new Hint('Both point up')];
        $puzzle = new Puzzle('clock', 'The clock', $key, $hints, Unlock::after(['door']));

        self::assertSame('clock', $puzzle->id);
        self::assertSame('The clock', $puzzle->title);
        self::assertSame($key, $puzzle->answer);
        self::assertSame($hints, $puzzle->hints);
        self::assertSame(['door'], $puzzle->unlock->requires);
        self::assertSame(60, $hints[0]->penalty);
        self::assertSame(0, $hints[1]->penalty);
        self::assertSame('Both point up', $hints[1]->text);
    }

    public function testAPuzzleIsOpenFromTheStartByDefault(): void
    {
        self::assertSame([], new Puzzle('clock', 'The clock', new AnswerKey(['a']))->unlock->requires);
        self::assertSame([], new Puzzle('clock', 'The clock', new AnswerKey(['a']))->hints);
    }

    public function testWithUnlockGivesTheSamePuzzleOpenedAnotherWay(): void
    {
        $hints = [new Hint('Look')];
        $puzzle = new Puzzle('clock', 'The clock', new AnswerKey(['a']), $hints);
        $moved = $puzzle->withUnlock(Unlock::after(['door']));

        self::assertSame(['door'], $moved->unlock->requires);
        self::assertSame([], $puzzle->unlock->requires);
        self::assertSame('clock', $moved->id);
        self::assertSame('The clock', $moved->title);
        self::assertSame($puzzle->answer, $moved->answer);
        self::assertSame($hints, $moved->hints);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function identifiers(): iterable
    {
        yield 'one letter' => ['a'];
        yield 'inner signs' => ['old_oak-2'];
        yield '64 characters' => [str_repeat('a', 64)];
    }

    #[DataProvider('identifiers')]
    public function testAnIdentifierIsTaken(string $id): void
    {
        self::assertSame($id, new Puzzle($id, 'T', new AnswerKey(['a']))->id);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'from a digit' => ['1clock'];
        yield 'upper case' => ['Clock'];
        yield 'ending with a sign' => ['clock-'];
        yield 'a space' => ['the clock'];
        yield '65 characters' => [str_repeat('a', 65)];
        yield 'a new line at the end' => ["clock\n"];
    }

    #[DataProvider('badIdentifiers')]
    public function testAnIdentifierOutsideThePatternIsRefused(string $id): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a puzzle holds 1 to 64 characters among a-z, 0-9, and inner - and _, from a letter;');

        new Puzzle($id, 'T', new AnswerKey(['a']));
    }

    public function testATitleOfTheLongestLengthIsTaken(): void
    {
        self::assertSame(str_repeat('é', 200), new Puzzle('a', str_repeat('é', 200), new AnswerKey(['a']))->title);
    }

    public function testALongerTitleIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('The title of the puzzle "a" holds 1 to 200 characters.');

        new Puzzle('a', str_repeat('é', 201), new AnswerKey(['a']));
    }

    public function testABlankTitleIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('The title of the puzzle "a" holds 1 to 200 characters.');

        new Puzzle('a', ' ', new AnswerKey(['a']));
    }

    public function testTenHintsAreTaken(): void
    {
        self::assertCount(10, new Puzzle('a', 'A', new AnswerKey(['a']), array_fill(0, 10, new Hint('h')))->hints);
    }

    public function testElevenHintsAreRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('The puzzle "a" has 10 hints at most.');

        new Puzzle('a', 'A', new AnswerKey(['a']), array_fill(0, 11, new Hint('h')));
    }

    public function testAPuzzleThatRequiresItselfIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('The puzzle "a" cannot require itself.');

        new Puzzle('a', 'A', new AnswerKey(['a']), [], Unlock::after(['b', 'a']));
    }

    public function testAHintOfTheLongestTextAndPenaltyIsTaken(): void
    {
        $hint = new Hint(str_repeat('é', 2000), 86_400);

        self::assertSame(86_400, $hint->penalty);
        self::assertSame(2000, mb_strlen($hint->text));
    }

    public function testABlankHintIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('A hint holds 1 to 2000 characters.');

        new Hint("\n ");
    }

    public function testALongerHintIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('A hint holds 1 to 2000 characters.');

        new Hint(str_repeat('é', 2001));
    }

    public function testANegativePenaltyIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('The penalty of a hint is 0 to 86400 seconds.');

        new Hint('h', -1);
    }

    public function testALongerPenaltyIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('The penalty of a hint is 0 to 86400 seconds.');

        new Hint('h', 86_401);
    }
}
