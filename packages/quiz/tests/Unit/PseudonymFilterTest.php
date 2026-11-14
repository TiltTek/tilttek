<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Quiz\Contract\PseudonymFilter;
use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Pseudonym\ChainFilter;
use Tilttek\Quiz\Pseudonym\WordListFilter;

#[CoversClass(WordListFilter::class)]
#[CoversClass(ChainFilter::class)]
final class PseudonymFilterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function pseudonyms(): iterable
    {
        yield 'a plain pseudonym' => ['Pixel', true];
        yield 'a name of the staff' => ['Admin', false];
        yield 'a name of the staff with stand-ins' => ['4dm1n', false];
        yield 'a name of the staff split' => ['Ad Min', false];
        yield 'a name of the staff among other words' => ['the host', false];
        yield 'a name of the staff in French with accents' => ['Modératrice', false];
        yield 'a whole word inside another' => ['Cassandra', true];
        yield 'a whole word inside a known town' => ['Scunthorpe', true];
        yield 'a whole word inside a French word' => ['Salopette', true];
        yield 'a whole word inside a ghost' => ['Ghost', true];
        yield 'a term anywhere' => ['FuckFace', false];
        yield 'a term anywhere with symbols' => ['sh!t happens', false];
        yield 'a term in full-width letters' => ['ＦＵＣＫ', false];
        yield 'a French term with its accent' => ['Câlisse', false];
        yield 'a term at the end' => ['bigshit', false];
        yield 'a term at the start' => ['shitbig', false];
        yield 'a term of the whole text alone' => ['shit', false];
        yield 'digits only' => ['1234', true];
        yield 'a player with a number' => ['Player1', true];
    }

    #[DataProvider('pseudonyms')]
    public function testTheLibrarysListRefusesItsWords(string $pseudonym, bool $allowed): void
    {
        self::assertSame($allowed, WordListFilter::defaults()->allows($pseudonym));
    }

    public function testTheApplicationAddsItsWords(): void
    {
        $filter = WordListFilter::defaults()->with(['Dragon', 'Wyvern'], ['Kobold', 'Goblin']);

        self::assertFalse($filter->allows('Admin'));
        self::assertFalse($filter->allows('Nazi'));
        self::assertFalse($filter->allows('Wyvern'));
        self::assertFalse($filter->allows('BigGoblinKing'));
        self::assertFalse($filter->allows('dragon'));
        self::assertTrue($filter->allows('Dragonfly'));
        self::assertFalse($filter->allows('BigKoboldKing'));
        self::assertFalse($filter->allows('shit'));
        self::assertTrue($filter->allows('Pixel'));
    }

    public function testAListOfItsOwnRefusesOnlyItsWords(): void
    {
        $filter = new WordListFilter(['Dragon']);

        self::assertTrue($filter->allows('Admin'));
        self::assertFalse($filter->allows('Dragon'));
        self::assertTrue($filter->allows('Dragons'));
        self::assertTrue(new WordListFilter([])->allows('Anything'));
    }

    public function testATermAnywhereIsLookedUpAtEveryLength(): void
    {
        $filter = new WordListFilter([], ['ab', 'cdef']);

        self::assertFalse($filter->allows('xxab'));
        self::assertFalse($filter->allows('abxx'));
        self::assertFalse($filter->allows('xcdefx'));
        self::assertFalse($filter->allows('cdef'));
        self::assertTrue($filter->allows('cdexf'));
        self::assertTrue($filter->allows('a'));
        self::assertTrue($filter->allows('xcde'));
    }

    public function testTooLongOrBrokenTextIsRefused(): void
    {
        $filter = new WordListFilter([]);

        self::assertTrue($filter->allows(str_repeat('a', WordListFilter::LONGEST)));
        self::assertFalse($filter->allows(str_repeat('a', WordListFilter::LONGEST + 1)));
        self::assertTrue($filter->allows(str_repeat('é', WordListFilter::LONGEST)));
        self::assertFalse($filter->allows("\xC3"));
    }

    public function testTheWordsOfAListAreChecked(): void
    {
        self::assertInstanceOf(WordListFilter::class, new WordListFilter([str_repeat('a', WordListFilter::LONGEST)], [str_repeat('é', WordListFilter::LONGEST)]));
        self::assertInstanceOf(WordListFilter::class, new WordListFilter(array_fill(0, WordListFilter::MOST_WORDS - 1, 'a'), ['b']));

        foreach ([
            'A word list holds at most 10000 words.' => static fn (): WordListFilter => new WordListFilter(array_fill(0, WordListFilter::MOST_WORDS, 'a'), ['b']),
            'A word of the list is text of at most 64 characters.' => static fn (): WordListFilter => new WordListFilter([str_repeat('a', WordListFilter::LONGEST + 1)]),
            'A word of the list holds at least one letter.' => static fn (): WordListFilter => new WordListFilter([], ['-']),
            'A word of the list is text of at most 64 characters. ' => static fn (): WordListFilter => new WordListFilter(["\xC3"]),
        ] as $message => $make) {
            try {
                $make();
                self::fail('A word list was accepted: '.$message);
            } catch (InvalidQuiz $invalidQuiz) {
                self::assertSame(trim($message), $invalidQuiz->getMessage());
            }
        }
    }

    public function testAChainAllowsWhatEveryFilterAllows(): void
    {
        $refusing = new class implements PseudonymFilter {
            /** @var list<string> */
            public array $asked = [];

            #[Override]
            public function allows(string $pseudonym): bool
            {
                $this->asked[] = $pseudonym;

                return 'Rude' !== $pseudonym;
            }
        };
        $chain = new ChainFilter(WordListFilter::defaults(), $refusing);

        self::assertTrue($chain->allows('Pixel'));
        self::assertFalse($chain->allows('Rude'));
        self::assertFalse($chain->allows('Admin'));
        self::assertSame(['Pixel', 'Rude'], $refusing->asked);
        self::assertTrue(new ChainFilter()->allows('Admin'));
    }
}
