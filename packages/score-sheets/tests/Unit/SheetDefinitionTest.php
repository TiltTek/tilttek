<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\ScoreSheets\Category;
use Tilttek\ScoreSheets\Direction;
use Tilttek\ScoreSheets\Exception\InvalidSheet;
use Tilttek\ScoreSheets\Name;
use Tilttek\ScoreSheets\Operation\Sum;
use Tilttek\ScoreSheets\SheetDefinition;
use Tilttek\ScoreSheets\TeamCombine;
use Tilttek\ScoreSheets\Tiebreaker;

#[CoversClass(SheetDefinition::class)]
#[CoversClass(Category::class)]
#[CoversClass(Tiebreaker::class)]
#[CoversClass(Name::class)]
#[CoversClass(InvalidSheet::class)]
final class SheetDefinitionTest extends TestCase
{
    public function testADeclarationIsReadFromJsonAndWrittenBack(): void
    {
        $sheet = LanternHarbor::sheet();

        self::assertSame('lantern-harbor', $sheet->game);
        self::assertSame(['boats', 'lanterns', 'harbor', 'sets', 'set_points', 'fleet', 'lighthouse', 'coins'], array_map(static fn (Category $category): string => $category->key, $sheet->categories()));
        self::assertSame('Boats moored', $sheet->category('boats')?->label);
        self::assertFalse($sheet->category('boats')->counted);
        self::assertTrue($sheet->category('harbor')?->counted);
        self::assertSame(0, $sheet->category('boats')->min);
        self::assertSame(12, $sheet->category('boats')->max);
        self::assertSame(1_000_000, $sheet->category('coins')?->max);
        self::assertTrue($sheet->category('boats')->isEntered());
        self::assertFalse($sheet->category('harbor')->isEntered());
        self::assertNull($sheet->category('none'));
        self::assertSame(TeamCombine::Sum, $sheet->teams);
        self::assertSame(Direction::Fewest, $sheet->tiebreakers[1]->prefer);

        $array = $sheet->toArray();
        self::assertSame(['key' => 'boats', 'label' => 'Boats moored', 'counted' => false, 'min' => 0, 'max' => 12], $array['categories'][0]);
        self::assertSame(['key' => 'harbor', 'label' => 'Harbour', 'counted' => true, 'operation' => ['type' => 'sum', 'of' => ['boats', 'lanterns']]], $array['categories'][2]);
        self::assertSame([['category' => 'coins', 'prefer' => 'most'], ['category' => 'boats', 'prefer' => 'fewest']], $array['tiebreakers']);
        self::assertSame('sum', $array['teams']);
        self::assertEquals($sheet, SheetDefinition::fromArray($array));
        self::assertEquals($sheet, SheetDefinition::fromJson(json_encode($array, \JSON_THROW_ON_ERROR)));
    }

    public function testTheDefaultsOfADeclaration(): void
    {
        $sheet = SheetDefinition::fromArray(['game' => 'g', 'categories' => [['key' => 'points']]]);
        $points = $sheet->category('points');

        self::assertSame('points', $points?->label);
        self::assertTrue($points->counted);
        self::assertSame(-1_000_000, $points->min);
        self::assertSame(1_000_000, $points->max);
        self::assertSame([], $sheet->tiebreakers);
        self::assertSame(TeamCombine::Sum, $sheet->teams);
        self::assertSame(TeamCombine::Best, SheetDefinition::fromArray(['game' => 'g', 'categories' => [['key' => 'p']], 'teams' => 'best'])->teams);
        self::assertSame(Direction::Most, SheetDefinition::fromArray(['game' => 'g', 'categories' => [['key' => 'p']], 'tiebreakers' => [['category' => 'p']]])->tiebreakers[0]->prefer);
        self::assertSame(-1_000_000, new Category('c', operation: new Sum(['a']))->min);
        self::assertSame(1_000_000, new Category('c', operation: new Sum(['a']))->max);
    }

    public function testASheetHoldsSixtyFourCategoriesAndEightTiebreakers(): void
    {
        $categories = array_map(static fn (int $index): Category => new Category('c'.$index), range(1, 64));
        $tiebreakers = array_map(static fn (int $index): Tiebreaker => new Tiebreaker('c'.$index), range(1, 8));

        self::assertCount(64, new SheetDefinition('g', $categories, $tiebreakers)->categories());

        foreach ([
            'A sheet declares 1 to 64 categories.' => static fn (): SheetDefinition => new SheetDefinition('g', []),
            'A sheet declares 1 to 64 categories. ' => static fn (): SheetDefinition => new SheetDefinition('g', [...$categories, new Category('c65')]),
            'A sheet declares at most 8 tie-breakers.' => static fn (): SheetDefinition => new SheetDefinition('g', $categories, [...$tiebreakers, new Tiebreaker('c9')]),
        ] as $message => $make) {
            try {
                $make();
                self::fail($message);
            } catch (InvalidSheet $invalid) {
                self::assertSame(trim($message), $invalid->getMessage());
            }
        }
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function badDeclarations(): iterable
    {
        yield 'twice' => [static fn (): SheetDefinition => new SheetDefinition('g', [new Category('a'), new Category('a')]), 'The category "a" is declared twice.'];
        yield 'read before declared' => [static fn (): SheetDefinition => new SheetDefinition('g', [new Category('t', operation: new Sum(['a'])), new Category('a')]), 'The category "t" reads "a", which is not declared before it.'];
        yield 'read itself' => [static fn (): SheetDefinition => new SheetDefinition('g', [new Category('t', operation: new Sum(['t']))]), 'The category "t" reads "t", which is not declared before it.'];
        yield 'unknown tie-breaker' => [static fn (): SheetDefinition => new SheetDefinition('g', [new Category('a')], [new Tiebreaker('b')]), 'The tie-breaker reads "b", which the sheet does not declare.'];
        yield 'game key' => [static fn (): SheetDefinition => new SheetDefinition('1g', [new Category('a')]), 'A game key is 1 to 64 ASCII letters, digits, dots, underscores and hyphens, starting with a letter.'];
        yield 'category key' => [static fn (): Category => new Category('a b'), 'A category key is 1 to 64 ASCII letters, digits, dots, underscores and hyphens, starting with a letter.'];
        yield 'category key of digits' => [static fn (): Category => new Category('10'), 'A category key is 1 to 64 ASCII letters, digits, dots, underscores and hyphens, starting with a letter.'];
        yield 'category key with a line break' => [static fn (): Category => new Category("a\n"), 'A category key is 1 to 64 ASCII letters, digits, dots, underscores and hyphens, starting with a letter.'];
        yield 'category key after a line' => [static fn (): Category => new Category("-\na"), 'A category key is 1 to 64 ASCII letters, digits, dots, underscores and hyphens, starting with a letter.'];
        yield 'tie-breaker key' => [static fn (): Tiebreaker => new Tiebreaker(''), 'A category key is 1 to 64 ASCII letters, digits, dots, underscores and hyphens, starting with a letter.'];
        yield 'control character' => [static fn (): Category => new Category('a', "Bo\x07ats"), 'The label of the category "a" is 1 to 100 characters, none of them a control character.'];
        yield 'long label' => [static fn (): Category => new Category('a', str_repeat('é', 101)), 'The label of the category "a" is 1 to 100 characters, none of them a control character.'];
        yield 'empty label' => [static fn (): Category => new Category('a', ''), 'The label of the category "a" is 1 to 100 characters, none of them a control character.'];
        yield 'label with a trailing line break' => [static fn (): Category => new Category('a', "Boats\n"), 'The label of the category "a" is 1 to 100 characters, none of them a control character.'];
        yield 'not UTF-8' => [static fn (): Category => new Category('a', "\xff"), 'The label of the category "a" is 1 to 100 characters, none of them a control character.'];
        yield 'computed with a minimum' => [static fn (): Category => new Category('a', operation: new Sum(['b']), min: 0), 'The computed category "a" takes no bounds.'];
        yield 'computed with a maximum' => [static fn (): Category => new Category('a', operation: new Sum(['b']), max: 0), 'The computed category "a" takes no bounds.'];
        yield 'minimum above maximum' => [static fn (): Category => new Category('a', min: 2, max: 1), 'The bounds of the category "a" lie between -1000000 and 1000000, the lower first.'];
        yield 'minimum too low' => [static fn (): Category => new Category('a', min: -1_000_001), 'The bounds of the category "a" lie between -1000000 and 1000000, the lower first.'];
        yield 'maximum too high' => [static fn (): Category => new Category('a', max: 1_000_001), 'The bounds of the category "a" lie between -1000000 and 1000000, the lower first.'];
    }

    #[DataProvider('badDeclarations')]
    public function testADeclarationThatDoesNotHoldTogetherIsRefused(callable $make, string $message): void
    {
        $this->expectExceptionObject(InvalidSheet::because($message));

        $make();
    }

    public function testTheBoundsMayBeEqualOrTheWidest(): void
    {
        $one = new Category('a', 'A label of 100 characters '.str_repeat('x', 74), min: 3, max: 3);
        self::assertSame([3, 3], [$one->min, $one->max]);
        self::assertSame(100, preg_match_all('/./su', $one->label));

        $widest = new Category('a', min: -1_000_000, max: 1_000_000);
        self::assertSame([-1_000_000, 1_000_000], [$widest->min, $widest->max]);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function badData(): iterable
    {
        $shape = 'A declaration has a game and a list of categories.';

        yield 'not an array' => ['sheet', $shape];
        yield 'no game' => [['categories' => []], $shape];
        yield 'game not a string' => [['game' => 1, 'categories' => []], $shape];
        yield 'no categories' => [['game' => 'g'], $shape];
        yield 'categories not a list' => [['game' => 'g', 'categories' => ['a' => []]], $shape];
        yield 'tie-breakers not a list' => [['game' => 'g', 'categories' => [['key' => 'a']], 'tiebreakers' => 'a'], 'The tie-breakers are a list.'];
        yield 'tie-breakers a map' => [['game' => 'g', 'categories' => [['key' => 'a']], 'tiebreakers' => ['x' => []]], 'The tie-breakers are a list.'];
        yield 'tie-breaker without category' => [['game' => 'g', 'categories' => [['key' => 'a']], 'tiebreakers' => [[]]], 'A tie-breaker has a category.'];
        yield 'tie-breaker not an object' => [['game' => 'g', 'categories' => [['key' => 'a']], 'tiebreakers' => ['a']], 'A tie-breaker has a category.'];
        yield 'tie-breaker preference' => [['game' => 'g', 'categories' => [['key' => 'a']], 'tiebreakers' => [['category' => 'a', 'prefer' => 'more']]], 'A tie-breaker prefers the most or the fewest.'];
        yield 'teams' => [['game' => 'g', 'categories' => [['key' => 'a']], 'teams' => 'average'], 'The teams combine by sum or best.'];
        yield 'category not an object' => [['game' => 'g', 'categories' => ['a']], 'A category has a key.'];
        yield 'category without key' => [['game' => 'g', 'categories' => [['label' => 'a']]], 'A category has a key.'];
        yield 'label not a string' => [['game' => 'g', 'categories' => [['key' => 'a', 'label' => 1]]], 'The label, counted, min or max of the category "a" is of the wrong type.'];
        yield 'counted not a boolean' => [['game' => 'g', 'categories' => [['key' => 'a', 'counted' => 'yes']]], 'The label, counted, min or max of the category "a" is of the wrong type.'];
        yield 'minimum not an integer' => [['game' => 'g', 'categories' => [['key' => 'a', 'min' => 1.5]]], 'The label, counted, min or max of the category "a" is of the wrong type.'];
        yield 'maximum not an integer' => [['game' => 'g', 'categories' => [['key' => 'a', 'max' => '9']]], 'The label, counted, min or max of the category "a" is of the wrong type.'];
        yield 'bad operation' => [['game' => 'g', 'categories' => [['key' => 'a', 'operation' => 'sum']]], 'An operation is an object.'];
    }

    #[DataProvider('badData')]
    public function testDataThatIsNotADeclarationIsRefused(mixed $data, string $message): void
    {
        $this->expectExceptionObject(InvalidSheet::because($message));

        SheetDefinition::fromArray($data);
    }

    public function testNullFieldsTakeTheirDefaults(): void
    {
        $sheet = SheetDefinition::fromArray(['game' => 'g', 'categories' => [['key' => 'a', 'label' => null, 'min' => null, 'max' => null, 'operation' => null]], 'teams' => null, 'tiebreakers' => [['category' => 'a', 'prefer' => null]]]);

        self::assertTrue($sheet->category('a')?->isEntered());
        self::assertSame('a', $sheet->category('a')->label);
    }

    public function testJsonIsBoundedAndChecked(): void
    {
        $long = '{"game": "g", "categories": [{"key": "a"}]}'.str_repeat(' ', SheetDefinition::MAX_JSON - 43);
        self::assertSame(SheetDefinition::MAX_JSON, \strlen($long));
        self::assertSame('g', SheetDefinition::fromJson($long)->game);

        try {
            SheetDefinition::fromJson($long.' ');
            self::fail('A long declaration was accepted.');
        } catch (InvalidSheet $invalidSheet) {
            self::assertSame('A declaration is at most 65536 bytes of JSON.', $invalidSheet->getMessage());
        }

        try {
            SheetDefinition::fromJson('{');
            self::fail('Broken JSON was accepted.');
        } catch (InvalidSheet $invalidSheet) {
            // The message of json_decode() itself differs between PHP versions.
            self::assertStringStartsWith('The declaration is not valid JSON: Syntax error', $invalidSheet->getMessage());
            self::assertStringEndsWith('.', $invalidSheet->getMessage());
        }

        self::assertSame('A declaration has a game and a list of categories.', $this->refusal(str_repeat('[', 15).str_repeat(']', 15)));
        self::assertStringStartsWith('The declaration is not valid JSON: Maximum stack depth exceeded', $this->refusal(str_repeat('[', 16).str_repeat(']', 16)));
    }

    public function testACategoryCountsTowardTheTotalUnlessToldOtherwise(): void
    {
        self::assertTrue(new Category('c')->counted);
        self::assertFalse(new Category('c', counted: false)->counted);
    }

    private function refusal(string $json): string
    {
        try {
            SheetDefinition::fromJson($json);
        } catch (InvalidSheet $invalidSheet) {
            return $invalidSheet->getMessage();
        }

        return '';
    }
}
