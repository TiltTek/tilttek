<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Definition\Attribution;
use Tilttek\Generator\Definition\Entry;
use Tilttek\Generator\Definition\EntryList;
use Tilttek\Generator\Definition\GeneratorDefinition;
use Tilttek\Generator\Draw;
use Tilttek\Generator\DrawResult;
use Tilttek\Generator\Engine\Producer;
use Tilttek\Generator\Exception\GenerationFailed;
use Tilttek\Generator\Exception\InvalidDefinition;
use Tilttek\Generator\Loading\Reader;
use Tilttek\Generator\Tests\Unit\Fixture\Catalogs;

/**
 * Where the text of a list, an entry or a generator comes from, read from a document and given with the results that
 * used it.
 */
#[CoversClass(Attribution::class)]
#[CoversClass(Entry::class)]
#[CoversClass(EntryList::class)]
#[CoversClass(GeneratorDefinition::class)]
#[CoversClass(Reader::class)]
#[CoversClass(DrawResult::class)]
#[CoversClass(Producer::class)]
final class AttributionTest extends TestCase
{
    public function testAnAttributionHasAtLeastOneText(): void
    {
        self::assertSame(['licence' => 'CC0-1.0'], new Attribution(licence: 'CC0-1.0')->toArray());
        self::assertSame(['source' => 's', 'licence' => 'l', 'attribution' => 'a'], new Attribution('s', 'l', 'a')->toArray());

        $this->expectExceptionObject(InvalidDefinition::at('attribution', 'an attribution has a source, a licence or an attribution'));

        new Attribution();
    }

    public function testEachTextIsShortNotEmptyAndInUtf8(): void
    {
        self::assertSame(str_repeat('é', 1024), new Attribution(source: str_repeat('é', 1024))->source);

        foreach (['', ' ', "\xC3", str_repeat('a', 1025)] as $text) {
            foreach (['source', 'licence', 'attribution'] as $name) {
                try {
                    new Attribution(...[$name => $text]);
                    self::fail(\sprintf('The %s took %s.', $name, var_export($text, true)));
                } catch (InvalidDefinition $invalidDefinition) {
                    self::assertSame(\sprintf('attribution: the %s is a text in UTF-8 of 1 to 1024 characters', $name), $invalidDefinition->getMessage());
                }
            }
        }
    }

    public function testAListAnEntryAndAGeneratorAreAttributedInADocument(): void
    {
        $catalog = Catalogs::of(
            ['l' => ['entries' => ['a', ['text' => 'b', 'source' => 'B', 'licence' => 'MIT']], 'source' => 'L', 'attribution' => 'By L'], 'plain' => ['x']],
            ['g' => ['templates' => 'x', 'licence' => 'CC-BY-4.0']],
        );

        self::assertEquals(new Attribution('L', null, 'By L'), $catalog->list('l')->attribution);
        self::assertNull($catalog->list('l')->entries[0]->attribution);
        self::assertEquals(new Attribution('B', 'MIT'), $catalog->list('l')->entries[1]->attribution);
        self::assertSame([], $catalog->list('l')->entries[1]->fields());
        self::assertNull($catalog->list('plain')->attribution);
        self::assertEquals(new Attribution(licence: 'CC-BY-4.0'), $catalog->generator('g')->attribution);
    }

    public function testAListOfOneDocumentIsAttributed(): void
    {
        $catalog = new Reader()->catalog(['f' => ['list' => 'l', 'entries' => ['a'], 'licence' => 'CC0-1.0', 'markov' => true]]);

        self::assertEquals(new Attribution(licence: 'CC0-1.0'), $catalog->list('l')->attribution);
        self::assertNotNull($catalog->list('l')->markov);
    }

    public function testAnAttributionIsMadeOfTexts(): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('test > lists > l', 'the source, the licence and the attribution are texts'));

        Catalogs::of(['l' => ['entries' => ['a'], 'source' => ['x']]]);
    }

    public function testAnInvalidAttributionSaysWhere(): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('test > lists > l > 0', 'attribution: the licence is a text in UTF-8 of 1 to 1024 characters'));

        Catalogs::of(['l' => ['entries' => [['text' => 'a', 'licence' => '']]]]);
    }

    public function testTheFieldsOfAnEntryCannotTakeTheNamesOfItsAttribution(): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('a', '"source" is not a field name'));

        new Entry('a', fields: ['source' => 'x']);
    }

    public function testADrawGivesTheAttributionsItUsedOnceInTheOrderItFirstUsedThem(): void
    {
        $generator = Catalogs::generator(
            [
                'l' => ['entries' => ['a', ['text' => 'b', 'source' => 'B']], 'source' => 'L'],
                'unused' => ['entries' => ['u'], 'source' => 'U'],
                'plain' => ['p'],
            ],
            ['g' => ['templates' => "{{ generate('h') }}{{ pick('l') }}{{ pick('l', 2)|join }}{{ pick('plain') }}", 'licence' => 'G'], 'h' => ['templates' => 'h', 'licence' => 'G']],
        );

        $result = $generator->draw('g', new Draw(2, seed: 'a'));

        self::assertEquals([new Attribution(licence: 'G'), new Attribution('L'), new Attribution('B')], $result->attributions);
        self::assertSame([['licence' => 'G'], ['source' => 'L'], ['source' => 'B']], $result->toArray()['attributions'] ?? null);
    }

    public function testADrawForgetsTheAttributionsOfTheDrawBefore(): void
    {
        $generator = Catalogs::generator(['l' => ['entries' => ['a'], 'source' => 'L']], ['g' => ['templates' => "{{ pick('l') }}"], 'h' => ['templates' => 'h']]);

        self::assertCount(1, $generator->draw('g')->attributions);
        self::assertSame([], $generator->draw('h')->attributions);
        self::assertArrayNotHasKey('attributions', $generator->draw('h')->toArray());
    }

    public function testAFailedDrawForgetsItsAttributions(): void
    {
        $generator = Catalogs::generator(['l' => ['entries' => ['a'], 'source' => 'L']], ['g' => ['templates' => "{{ pick('l') }}{{ nothing }}"], 'h' => ['templates' => 'h']]);

        try {
            $generator->draw('g');
            self::fail('The draw did not fail.');
        } catch (GenerationFailed) {
        }

        self::assertSame([], $generator->draw('h')->attributions);
    }

    public function testAWordInventedByAChainIsAttributedToItsList(): void
    {
        $generator = Catalogs::generator(['n' => ['entries' => ['alara', 'belor', 'carim'], 'markov' => ['allow_entries' => true], 'licence' => 'CC0-1.0']], ['g' => ['templates' => "{{ pick('n') }}"]]);

        self::assertEquals([new Attribution(licence: 'CC0-1.0')], $generator->draw('g', new Draw(seed: 'a'))->attributions);
    }

    public function testATableWithAMemoryGivesItsAttributions(): void
    {
        $generator = Catalogs::generator(['l' => ['entries' => ['sun'], 'source' => 'Weather']], ['w' => ['state' => ['sky' => "{{ pick('l') }}"], 'templates' => '{{ state.sky }}', 'attribution' => 'By us']]);

        self::assertEquals([new Attribution(attribution: 'By us'), new Attribution('Weather')], $generator->draw('w')->attributions);
    }
}
