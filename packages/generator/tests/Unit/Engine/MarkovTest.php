<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Engine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Generator\Definition\EntryList;
use Tilttek\Generator\Definition\MarkovChain;
use Tilttek\Generator\Draw;
use Tilttek\Generator\Engine\MarkovModel;
use Tilttek\Generator\Engine\Producer;
use Tilttek\Generator\Exception\ExhaustedList;
use Tilttek\Generator\Exception\LimitExceeded;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Limits;
use Tilttek\Generator\Tests\Unit\Fixture\Catalogs;
use Tilttek\Generator\Tests\Unit\Fixture\ScriptedEngine;

#[CoversClass(Generator::class)]
#[CoversClass(Producer::class)]
#[CoversClass(MarkovModel::class)]
#[CoversClass(MarkovChain::class)]
#[CoversClass(EntryList::class)]
#[CoversClass(ExhaustedList::class)]
final class MarkovTest extends TestCase
{
    private const array NAMES = ['Aldrin', 'Belisar', 'Corvane', 'Doran', 'Edrimar', 'Falkor', 'Garamond', 'Halvard', 'Isolde', 'Jorund'];

    public function testAMarkovListInventsWordsThatAreNotItsEntries(): void
    {
        $generator = Catalogs::generator(['n' => ['entries' => self::NAMES, 'markov' => ['order' => 2, 'min_length' => 4, 'max_length' => 8]]], ['g' => ['templates' => "{{ pick('n') }}"]], new Randomizer(new Xoshiro256StarStar(7)));
        $results = $generator->draw('g', new Draw(40))->results;

        self::assertCount(40, $results);

        foreach ($results as $result) {
            self::assertIsString($result);
            self::assertNotContains($result, self::NAMES);
            self::assertMatchesRegularExpression('/^[A-Z][a-z]{3,7}$/', $result);
        }
    }

    public function testAChainThatAllowsItsEntriesMayGiveThemBack(): void
    {
        $generator = Catalogs::generator(['n' => ['entries' => ['abc'], 'markov' => ['order' => 1, 'allow_entries' => true]]], ['g' => ['templates' => "{{ pick('n') }}"]], ScriptedEngine::randomizer());

        self::assertSame('abc', $generator->draw('g')->first());
    }

    public function testAListThatInventsNothingNewIsExhausted(): void
    {
        $generator = Catalogs::generator(['n' => ['entries' => ['Abc'], 'markov' => ['order' => 1]]], ['g' => ['templates' => "{{ pick('n') }}"]]);

        $this->expectExceptionObject(ExhaustedList::invented('n', 50));
        $generator->draw('g');
    }

    public function testEachAttemptCountsAsAPick(): void
    {
        $generator = Catalogs::generator(['n' => ['entries' => ['abc'], 'markov' => ['order' => 1]]], ['g' => ['templates' => "{{ pick('n') }}"]], limits: new Limits(picks: 49));

        $this->expectExceptionObject(LimitExceeded::of('picks per result', 49));
        $generator->draw('g');
    }

    public function testAPickMakesNoMoreAttemptsThanItsShare(): void
    {
        // The draw enters the generator (one pick), then the pick makes its 50 attempts: 51 picks, within the limit.
        $generator = Catalogs::generator(['n' => ['entries' => ['abc'], 'markov' => ['order' => 1]]], ['g' => ['templates' => "{{ pick('n') }}"]], limits: new Limits(picks: 51));

        $this->expectExceptionObject(ExhaustedList::invented('n', 50));
        $generator->draw('g');
    }

    public function testSeveralWordsTakeTheAttemptsOfEach(): void
    {
        // Two words take 100 attempts: with the pick that enters the generator, 101 picks, over the limit of 100.
        $generator = Catalogs::generator(['n' => ['entries' => ['abc'], 'markov' => ['order' => 1]]], ['g' => ['templates' => "{{ pick('n', 2)|length }}"]], limits: new Limits(picks: 100));

        $this->expectExceptionObject(LimitExceeded::of('picks per result', 100));
        $generator->draw('g');
    }

    public function testAnEntryIsKnownWhateverTheCaseOfItsLetters(): void
    {
        $generator = Catalogs::generator(['n' => ['entries' => ['Élan'], 'markov' => ['order' => 1]]], ['g' => ['templates' => "{{ pick('n') }}"]]);

        $this->expectExceptionObject(ExhaustedList::invented('n', 50));
        $generator->draw('g');
    }

    public function testAnExcludedWordIsExcludedWhateverTheCaseOfItsLetters(): void
    {
        $lists = ['n' => ['entries' => ['Élan'], 'markov' => ['order' => 1, 'allow_entries' => true]]];
        $generator = Catalogs::generator($lists, ['g' => ['templates' => "{{ pick('n', not: ['ÉLAN']) }}"]]);

        $this->expectExceptionObject(ExhaustedList::invented('n', 50));
        $generator->draw('g');
    }

    public function testAnInventedWordWeighsOne(): void
    {
        $lists = ['n' => ['entries' => [['text' => 'ab', 'weight' => 5]], 'markov' => ['order' => 1, 'min_length' => 1, 'allow_entries' => true]]];

        self::assertSame('1', Catalogs::generator($lists, ['g' => ['templates' => "{{ pick('n').weight }}"]])->draw('g')->first());
    }

    public function testSeveralWordsAreDifferentAndNoneIsExcluded(): void
    {
        // a is followed by b, c or d, each once: the second attempt draws b again, which is taken, the third c, excluded.
        $lists = ['n' => ['entries' => ['ab', 'ac', 'ad'], 'markov' => ['order' => 1, 'min_length' => 1, 'allow_entries' => true]]];
        $generator = Catalogs::generator($lists, ['g' => ['templates' => "{{ pick('n', 2, not: ['AC'])|join(',') }}"]], ScriptedEngine::randomizer(0, 0, 1, 2));

        self::assertSame('ab,ad', $generator->draw('g')->first());
    }

    public function testSeveralWordsMayBeFewerThanAskedFor(): void
    {
        $lists = ['n' => ['entries' => ['ab'], 'markov' => ['order' => 1, 'min_length' => 1, 'allow_entries' => true]]];
        $generator = Catalogs::generator($lists, ['g' => ['templates' => "{{ pick('n', 3)|join(',') }}|{{ pick('n', 2, not: 'ab')|length }}"]]);

        self::assertSame('ab|0', $generator->draw('g')->first());
    }

    public function testTheTagsOfAPickChooseTheEntriesTheChainLearnsFrom(): void
    {
        $lists = ['n' => ['entries' => [['text' => 'aaa', 'tags' => ['g' => 'f']], ['text' => 'bbb', 'tags' => ['g' => 'm']]], 'markov' => ['order' => 1, 'min_length' => 1, 'max_length' => 2]]];
        $generators = ['g' => ['templates' => "{% set w = pick('n', where: {g: 'm'}) %}{{ w }} {{ w.tags.g|join }}|{{ pick('n', 1, where: {g: 'f'})|join }}"]];

        self::assertSame('bb m|aa', Catalogs::generator($lists, $generators, ScriptedEngine::randomizer(0, 0))->draw('g')->first());
    }

    public function testATagNoEntryMatchesGivesNothing(): void
    {
        $lists = ['n' => ['entries' => [['text' => 'aaa', 'tags' => ['g' => 'f']]], 'markov' => true]];

        self::assertSame('[]', Catalogs::generator($lists, ['g' => ['templates' => "[{{ pick('n', 2, where: {g: 'm'})|join }}]"]])->draw('g')->first());

        $this->expectExceptionObject(ExhaustedList::named('n'));
        Catalogs::generator($lists, ['g' => ['templates' => "{{ pick('n', where: {g: 'm'}) }}"]])->draw('g');
    }

    public function testAnInventedWordTakesTheGrammarOfItsTags(): void
    {
        $lists = ['n' => ['entries' => [['text' => 'bo', 'tags' => ['gender' => 'f']]], 'markov' => ['order' => 1, 'min_length' => 1, 'allow_entries' => true]]];
        $generator = Catalogs::generator($lists, ['g' => ['locale' => 'fr', 'templates' => "{{ pick('n', where: {gender: 'f'})|article }}"]]);

        self::assertSame('la bo', $generator->draw('g')->first());
    }

    public function testASeededDrawInventsTheSameWords(): void
    {
        $generator = Catalogs::generator(['n' => ['entries' => self::NAMES, 'markov' => true]], ['g' => ['templates' => "{{ pick('n') }}"]]);

        self::assertSame($generator->draw('g', new Draw(10, seed: 'x'))->results, $generator->draw('g', new Draw(10, seed: 'x'))->results);
    }
}
