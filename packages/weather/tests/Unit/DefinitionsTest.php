<?php

declare(strict_types=1);

namespace Tilttek\Weather\Tests\Unit;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Weather\Condition;
use Tilttek\Weather\Exception\InvalidClimate;
use Tilttek\Weather\Rules;
use Tilttek\Weather\Season;
use Tilttek\Weather\SeasonalClimate;
use Tilttek\Weather\Zone;

#[CoversClass(Condition::class)]
#[CoversClass(Season::class)]
#[CoversClass(SeasonalClimate::class)]
#[CoversClass(Zone::class)]
#[CoversClass(Rules::class)]
#[CoversClass(InvalidClimate::class)]
final class DefinitionsTest extends TestCase
{
    private const string IDENTIFIER = 'is not an identifier: a lowercase letter, then up to 31 lowercase letters, digits, "_" or "-"';

    private const string LANGUAGE = 'is not a language code such as "en" or "fr_CA"';

    public function testAConditionKeepsItsLabelsShiftAndNeighbours(): void
    {
        $condition = new Condition('fog', ['en' => 'fog', 'fr_CA' => 'brume'], -20, ['drizzle', 'frost', 'drizzle']);

        self::assertSame('fog', $condition->id);
        self::assertSame(['en' => 'fog', 'fr_CA' => 'brume'], $condition->labels);
        self::assertSame(-20, $condition->temperature);
        self::assertSame(['drizzle', 'frost'], $condition->neighbours);
        self::assertSame(0, new Condition('a', ['en' => 'a'])->temperature);
        self::assertSame([], new Condition('a', ['en' => 'a'])->neighbours);
        self::assertSame(['b', 'c'], new Condition('a', ['en' => 'a'], 0, ['b', 'b', 'c'])->neighbours);
        self::assertSame(20, new Condition('b', ['en' => 'b'], 20)->temperature);
        self::assertSame(20, Condition::TEMPERATURE);
    }

    public function testASeasonKeepsItsLabels(): void
    {
        $season = new Season('wet', ['en' => 'wet season']);

        self::assertSame('wet', $season->id);
        self::assertSame(['en' => 'wet season'], $season->labels);
    }

    public function testASeasonalClimateKeepsItsRangeAndWeights(): void
    {
        $climate = new SeasonalClimate('summer', -80, 80, ['clear' => 1, 'fair' => 1000]);

        self::assertSame('summer', $climate->season);
        self::assertSame([-80, 80], [$climate->low, $climate->high]);
        self::assertSame(['clear' => 1, 'fair' => 1000], $climate->conditions);
        self::assertSame(0, $climate->middle());
        self::assertSame(-3, new SeasonalClimate('s', -4, -1, ['a' => 1])->middle());
        self::assertSame(2, new SeasonalClimate('s', 1, 4, ['a' => 1])->middle());
        self::assertSame(5, new SeasonalClimate('s', 5, 5, ['a' => 1])->middle());
        self::assertSame([80, 1000], [SeasonalClimate::TEMPERATURE, SeasonalClimate::WEIGHT]);
    }

    public function testAZoneKeepsItsSeasonsInOrder(): void
    {
        $zone = new Zone('arid', ['en' => 'arid'], [new SeasonalClimate('hot', 25, 45, ['clear' => 1]), new SeasonalClimate('cool', 5, 25, ['clear' => 1])], 20, '2d6-7');

        self::assertSame('arid', $zone->id);
        self::assertSame(['en' => 'arid'], $zone->labels);
        self::assertSame(['hot', 'cool'], array_keys($zone->seasons));
        self::assertSame('hot', $zone->firstSeason());
        self::assertSame(20, $zone->persistence);
        self::assertSame('2d6-7', $zone->change);

        $default = new Zone('z', ['en' => 'z'], [new SeasonalClimate('s', 0, 1, ['a' => 1])]);

        self::assertSame([3, '1d5-3'], [$default->persistence, $default->change]);
        self::assertSame(1, new Zone('z', ['en' => 'z'], [new SeasonalClimate('s', 0, 1, ['a' => 1])], 1)->persistence);
        self::assertSame([16, 20], [Zone::SEASONS, Zone::PERSISTENCE]);
        self::assertCount(16, new Zone('z', ['en' => 'z'], self::seasons(16))->seasons);
    }

    /**
     * @return iterable<string, array{Closure(): mixed, non-empty-string}>
     */
    public static function invalid(): iterable
    {
        yield 'identifier' => [static fn (): Condition => new Condition('Fog', ['en' => 'fog']), 'conditions: "Fog" '.self::IDENTIFIER];
        yield 'identifier too long' => [static fn (): Season => new Season('a'.str_repeat('b', 32), ['en' => 'a']), 'seasons: "a'.str_repeat('b', 32).'" '.self::IDENTIFIER];
        yield 'identifier with a line break' => [static fn (): Season => new Season("a\n", ['en' => 'a']), "seasons: \"a\n\" ".self::IDENTIFIER];
        yield 'identifier with text before' => [static fn (): Season => new Season('-a', ['en' => 'a']), 'seasons: "-a" '.self::IDENTIFIER];
        yield 'no label' => [static fn (): Season => new Season('a', []), 'seasons.a.labels: at least one label is given'];
        yield 'language' => [static fn (): Season => new Season('a', ['english' => 'a']), 'seasons.a.labels: "english" '.self::LANGUAGE];
        yield 'language number' => [static fn (): Season => new Season('a', ['a']), 'seasons.a.labels: "0" '.self::LANGUAGE];
        yield 'language with text after' => [static fn (): Season => new Season('a', ['fr_CAN' => 'a']), 'seasons.a.labels: "fr_CAN" '.self::LANGUAGE];
        yield 'language with a line break' => [static fn (): Season => new Season('a', ["fr\n" => 'a']), "seasons.a.labels: \"fr\n\" ".self::LANGUAGE];
        yield 'language with text before' => [static fn (): Season => new Season('a', ['en' => 'a', '_fr' => 'a']), 'seasons.a.labels: "_fr" '.self::LANGUAGE];
        yield 'empty label' => [static fn (): Season => new Season('a', ['en' => ' ']), 'seasons.a.labels.en: a label is a text of 1 to 100 characters'];
        yield 'long label' => [static fn (): Season => new Season('a', ['en' => str_repeat('é', 101)]), 'seasons.a.labels.en: a label is a text of 1 to 100 characters'];
        yield 'label not a text' => [static fn (): Season => new Season('a', ['en' => 3]), 'seasons.a.labels.en: a label is a text of 1 to 100 characters'];
        yield 'shift' => [static fn (): Condition => new Condition('a', ['en' => 'a'], 21), 'conditions.a.temperature: 21 is not a whole number from -20 to 20'];
        yield 'shift down' => [static fn (): Condition => new Condition('a', ['en' => 'a'], -21), 'conditions.a.temperature: -21 is not a whole number from -20 to 20'];
        yield 'own neighbour' => [static fn (): Condition => new Condition('a', ['en' => 'a'], 0, ['b', 'a']), 'conditions.a.next_to: a condition is not its own neighbour: it may always stay'];
        yield 'neighbour identifier' => [static fn (): Condition => new Condition('a', ['en' => 'a'], 0, ['B']), 'conditions.a.next_to: "B" '.self::IDENTIFIER];
        yield 'condition labels' => [static fn (): Condition => new Condition('a', []), 'conditions.a.labels: at least one label is given'];
        yield 'low' => [static fn (): SeasonalClimate => new SeasonalClimate('s', -81, 0, ['a' => 1], 'here'), 'here.low: -81 is not a whole number from -80 to 80'];
        yield 'high' => [static fn (): SeasonalClimate => new SeasonalClimate('s', 0, 81, ['a' => 1]), 'season.high: 81 is not a whole number from 0 to 80'];
        yield 'range' => [static fn (): SeasonalClimate => new SeasonalClimate('s', 5, 4, ['a' => 1]), 'season.high: 4 is not a whole number from 5 to 80'];
        yield 'no condition' => [static fn (): SeasonalClimate => new SeasonalClimate('s', 0, 1, []), 'season.conditions: a season brings at least one condition'];
        yield 'condition identifier' => [static fn (): SeasonalClimate => new SeasonalClimate('s', 0, 1, ['A' => 1]), 'season.conditions: "A" '.self::IDENTIFIER];
        yield 'weight' => [static fn (): SeasonalClimate => new SeasonalClimate('s', 0, 1, ['a' => 0]), 'season.conditions.a: 0 is not a whole number from 1 to 1000'];
        yield 'heavy weight' => [static fn (): SeasonalClimate => new SeasonalClimate('s', 0, 1, ['a' => 1001]), 'season.conditions.a: 1001 is not a whole number from 1 to 1000'];
        yield 'weight not a number' => [static fn (): SeasonalClimate => new SeasonalClimate('s', 0, 1, ['a' => '2']), 'season.conditions.a: a weight is a whole number from 1 to 1000'];
        yield 'zone identifier' => [static fn (): Zone => new Zone('Z', ['en' => 'z'], []), 'zones: "Z" '.self::IDENTIFIER];
        yield 'zone labels' => [static fn (): Zone => new Zone('z', [], []), 'zones.z.labels: at least one label is given'];
        yield 'persistence' => [static fn (): Zone => new Zone('z', ['en' => 'z'], [], 0), 'zones.z.persistence: 0 is not a whole number from 1 to 20'];
        yield 'strong persistence' => [static fn (): Zone => new Zone('z', ['en' => 'z'], [], 21), 'zones.z.persistence: 21 is not a whole number from 1 to 20'];
        yield 'change' => [static fn (): Zone => new Zone('z', ['en' => 'z'], [], 3, 'two dice'), 'zones.z.change: "two dice" is not a roll of tilttek/dice: '];
        yield 'several totals' => [static fn (): Zone => new Zone('z', ['en' => 'z'], [], 3, '1d6, 1d8'), 'zones.z.change: "1d6, 1d8" does not roll one whole total'];
        yield 'no season' => [static fn (): Zone => new Zone('z', ['en' => 'z'], []), 'zones.z.seasons: a zone has 1 to 16 seasons'];
        yield 'too many seasons' => [static fn (): Zone => new Zone('z', ['en' => 'z'], self::seasons(17)), 'zones.z.seasons: a zone has 1 to 16 seasons'];
        yield 'season twice' => [static fn (): Zone => new Zone('z', ['en' => 'z'], self::seasons(1, 's1')), 'zones.z.seasons.s1: a season comes once in a zone'];
        yield 'season identifier' => [static fn (): Zone => new Zone('z', ['en' => 'z'], [new SeasonalClimate('S', 0, 1, ['a' => 1])]), 'zones.z.seasons: "S" '.self::IDENTIFIER];
    }

    /**
     * @param Closure(): mixed $build
     * @param non-empty-string $message
     */
    #[DataProvider('invalid')]
    public function testAnInvalidDefinitionNamesItsPlace(Closure $build, string $message): void
    {
        try {
            $build();
            self::fail('The definition is taken.');
        } catch (InvalidClimate $invalidClimate) {
            self::assertStringStartsWith($message, $invalidClimate->getMessage());
        }
    }

    public function testTheRulesHaveTheirBounds(): void
    {
        self::assertSame(100, Rules::LABEL_LENGTH);
        self::assertSame(['en' => str_repeat('é', 100)], Rules::labels('x', ['en' => str_repeat('é', 100)]));
        self::assertSame('a'.str_repeat('b', 31), Rules::identifier('x', 'a'.str_repeat('b', 31)));
        self::assertSame('a-b_9', Rules::identifier('x', 'a-b_9'));
        self::assertSame(5, Rules::between('x', 5, 5, 5));
        self::assertSame(['fr_CA' => 'x', 'fra' => 'y'], Rules::labels('x', ['fr_CA' => 'x', 'fra' => 'y']));
    }

    /**
     * @return list<SeasonalClimate>
     */
    private static function seasons(int $count, ?string $repeated = null): array
    {
        $seasons = array_map(static fn (int $i): SeasonalClimate => new SeasonalClimate('s'.$i, 0, 1, ['a' => 1]), range(1, $count));

        return null === $repeated ? $seasons : [...$seasons, new SeasonalClimate($repeated, 0, 1, ['a' => 1])];
    }
}
