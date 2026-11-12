<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Engine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Definition\Entry;
use Tilttek\Generator\Definition\MarkovChain;
use Tilttek\Generator\Engine\MarkovModel;
use Tilttek\Generator\Tests\Unit\Fixture\ScriptedEngine;

#[CoversClass(MarkovModel::class)]
#[CoversClass(MarkovChain::class)]
final class MarkovModelTest extends TestCase
{
    public function testALetterWithOneFollowerTakesNoRandomness(): void
    {
        $model = MarkovModel::learn(new MarkovChain(1, 1), [new Entry('abc')]);

        self::assertSame('abc', $model->word(ScriptedEngine::randomizer()));
        self::assertEquals(new MarkovChain(1, 1), $model->chain);
    }

    public function testTheFollowersAreChosenByTheirCounts(): void
    {
        $model = MarkovModel::learn(new MarkovChain(1, 1), [new Entry('ab'), new Entry('ac', 3)]);

        self::assertSame('ab', $model->word(ScriptedEngine::randomizer(0)));
        self::assertSame('ac', $model->word(ScriptedEngine::randomizer(1)));
        self::assertSame('ac', $model->word(ScriptedEngine::randomizer(3)));
    }

    public function testTheCountsOfAFollowerAddUp(): void
    {
        // After a, b counts 2 and c counts 1: the roll of 2 still falls on b.
        $model = MarkovModel::learn(new MarkovChain(1, 1), [new Entry('ab'), new Entry('ab'), new Entry('ac')]);

        self::assertSame('ab', $model->word(ScriptedEngine::randomizer(1)));
    }

    public function testADigitChosenAmongFollowersIsALetter(): void
    {
        self::assertSame('12', MarkovModel::learn(new MarkovChain(1, 1), [new Entry('12'), new Entry('13')])->word(ScriptedEngine::randomizer(0)));
    }

    public function testTheLengthsCountLettersAndNotBytes(): void
    {
        self::assertSame('ééé', MarkovModel::learn(new MarkovChain(1, 3, 3), [new Entry('ééé')])->word(ScriptedEngine::randomizer()));
    }

    public function testTheOrderIsTheLengthOfTheRunThatChoosesTheNextLetter(): void
    {
        $entries = [new Entry('abx'), new Entry('cby')];

        // Order 1: after b comes x or y, whatever came before.
        self::assertSame('abx', MarkovModel::learn(new MarkovChain(1, 1), $entries)->word(ScriptedEngine::randomizer(0, 0)));
        self::assertSame('aby', MarkovModel::learn(new MarkovChain(1, 1), $entries)->word(ScriptedEngine::randomizer(0, 1)));
        // Order 2: after "ab" only x comes.
        self::assertSame('abx', MarkovModel::learn(new MarkovChain(2, 1), $entries)->word(ScriptedEngine::randomizer(0)));
        self::assertSame('cby', MarkovModel::learn(new MarkovChain(2, 1), $entries)->word(ScriptedEngine::randomizer(1)));
    }

    public function testAWordIsAtLeastTheShortestLength(): void
    {
        $model = MarkovModel::learn(new MarkovChain(1, 2), [new Entry('a'), new Entry('ab')]);

        self::assertSame('ab', $model->word(ScriptedEngine::randomizer()));
        self::assertNull(MarkovModel::learn(new MarkovChain(1, 2), [new Entry('a')])->word(ScriptedEngine::randomizer()));
        self::assertSame('a', MarkovModel::learn(new MarkovChain(1, 1), [new Entry('a')])->word(ScriptedEngine::randomizer()));
    }

    public function testAWordIsAtMostTheLongestLength(): void
    {
        self::assertNull(MarkovModel::learn(new MarkovChain(1, 1, 2), [new Entry('abc')])->word(ScriptedEngine::randomizer()));
        self::assertSame('ab', MarkovModel::learn(new MarkovChain(1, 1, 2), [new Entry('ab')])->word(ScriptedEngine::randomizer()));
        self::assertSame('ab', MarkovModel::learn(new MarkovChain(1, 1, 2), [new Entry('abc'), new Entry('b')])->word(ScriptedEngine::randomizer(0)));
    }

    public function testWordsAreCapitalisedWhenEveryEntryIs(): void
    {
        self::assertSame('Éva', MarkovModel::learn(new MarkovChain(1, 1), [new Entry('Éva')])->word(ScriptedEngine::randomizer()));
        self::assertSame('éva', MarkovModel::learn(new MarkovChain(1, 1), [new Entry('Éva'), new Entry('ox')])->word(ScriptedEngine::randomizer(0)));
        self::assertSame('éva', MarkovModel::learn(new MarkovChain(1, 1), [new Entry('ox'), new Entry('Éva')])->word(ScriptedEngine::randomizer(1)));
        self::assertSame('12', MarkovModel::learn(new MarkovChain(1, 1), [new Entry('12')])->word(ScriptedEngine::randomizer()));
        // Only the first letter of an entry says whether it is capitalised, read as a letter and not as a byte.
        self::assertSame('ab', MarkovModel::learn(new MarkovChain(1, 1), [new Entry('aB')])->word(ScriptedEngine::randomizer()));
        self::assertSame('éva', MarkovModel::learn(new MarkovChain(1, 1), [new Entry('éva')])->word(ScriptedEngine::randomizer()));
        self::assertNull(MarkovModel::learn(new MarkovChain(1, 1), [])->word(ScriptedEngine::randomizer()));
    }

    public function testTheMarksOfAWordCannotComeFromItsEntries(): void
    {
        $model = MarkovModel::learn(new MarkovChain(1, 1), [new Entry("a\u{3}b\u{2}c")]);

        self::assertSame('abc', $model->word(ScriptedEngine::randomizer()));
        self::assertTrue($model->knows('abc'));
    }

    public function testItKnowsItsEntriesInLowerCase(): void
    {
        $model = MarkovModel::learn(new MarkovChain(), [new Entry('Aldrin')]);

        self::assertTrue($model->knows('aldrin'));
        self::assertFalse($model->knows('Aldrin'));
        self::assertFalse($model->knows('aldri'));
    }
}
