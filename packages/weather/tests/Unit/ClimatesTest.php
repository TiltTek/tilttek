<?php

declare(strict_types=1);

namespace Tilttek\Weather\Tests\Unit;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Weather\ClimateReader;
use Tilttek\Weather\Climates;
use Tilttek\Weather\Condition;
use Tilttek\Weather\Exception\InvalidClimate;
use Tilttek\Weather\Exception\UnknownClimate;
use Tilttek\Weather\Season;
use Tilttek\Weather\SeasonalClimate;
use Tilttek\Weather\Zone;

#[CoversClass(Climates::class)]
#[CoversClass(ClimateReader::class)]
#[CoversClass(UnknownClimate::class)]
final class ClimatesTest extends TestCase
{
    public function testTheShippedClimates(): void
    {
        $climates = Climates::default();

        self::assertSame(\dirname(__DIR__, 2).'/data/climates.yaml', Climates::file());
        self::assertSame(['en', 'fr'], $climates->locales);
        self::assertCount(16, $climates->conditions());
        self::assertSame(['spring', 'summer', 'autumn', 'winter', 'wet', 'dry', 'hot', 'cool'], array_keys($climates->seasons()));
        self::assertSame(['temperate', 'boreal', 'mediterranean', 'arid', 'tropical'], array_keys($climates->zones()));
        self::assertSame('ciel dégagé', $climates->condition('clear')->labels['fr']);
        self::assertSame('wet season', $climates->season('wet')->labels['en']);
        self::assertSame('tempéré', $climates->zone('temperate')->labels['fr']);
        self::assertSame([-8, 6], [$climates->climate('temperate', 'winter')->low, $climates->climate('temperate', 'winter')->high]);
        self::assertSame(['fair', 'frost', 'heatwave'], $climates->neighbours('clear'));
        self::assertSame(['clear', 'fog'], $climates->neighbours('frost'));
        self::assertSame('fr', $climates->locale('fr'));

        foreach ($climates->conditions() as $condition) {
            foreach ($climates->neighbours($condition->id) as $neighbour) {
                self::assertContains($condition->id, $climates->neighbours($neighbour), $neighbour);
            }

            self::assertNotSame([], $climates->neighbours($condition->id), $condition->id);
        }
    }

    public function testUnknownNamesAreErrors(): void
    {
        $climates = Climates::default();
        $calls = [
            'The condition "hail" is not declared; the climates know "clear", ' => static fn (): mixed => $climates->condition('hail'),
            'The season "monsoon" is not declared; the climates know "spring", "summer", "autumn", "winter", "wet", "dry", "hot", "cool".' => static fn (): mixed => $climates->season('monsoon'),
            'The zone "polar" is not declared; the climates know "temperate", "boreal", "mediterranean", "arid", "tropical".' => static fn (): mixed => $climates->zone('polar'),
            'The season of the zone "arid" "summer" is not declared; the climates know "hot", "cool".' => static fn (): mixed => $climates->climate('arid', 'summer'),
            'The zone "polar" is not declared' => static fn (): mixed => $climates->climate('polar', 'summer'),
            'The condition "hail" is not declared' => static fn (): mixed => $climates->neighbours('hail'),
            'The language "de" is not declared; the climates know "en", "fr".' => static fn (): mixed => $climates->locale('de'),
        ];

        foreach ($calls as $message => $call) {
            try {
                $call();
                self::fail($message);
            } catch (UnknownClimate $unknown) {
                self::assertStringStartsWith($message, $unknown->getMessage());
            }
        }
    }

    /**
     * @return iterable<string, array{Closure(): mixed, non-empty-string}>
     */
    public static function invalid(): iterable
    {
        $condition = new Condition('clear', ['en' => 'clear']);
        $season = new Season('summer', ['en' => 'summer']);
        $zone = new Zone('temperate', ['en' => 'temperate'], [new SeasonalClimate('summer', 10, 20, ['clear' => 1])]);

        yield 'no language' => [static fn (): Climates => new Climates([], [$condition], [$season], [$zone]), 'locales: the labels are written in 1 to 8 languages'];
        yield 'too many languages' => [static fn (): Climates => new Climates(['aa', 'ab', 'ac', 'ad', 'ae', 'af', 'ag', 'ah', 'ai'], [$condition], [$season], [$zone]), 'locales: the labels are written in 1 to 8 languages'];
        yield 'language twice' => [static fn (): Climates => new Climates(['en', 'en'], [$condition], [$season], [$zone]), 'locales: a language is named once'];
        yield 'language code' => [static fn (): Climates => new Climates(['English'], [$condition], [$season], [$zone]), 'locales: "English" is not a language code'];
        yield 'missing label' => [static fn (): Climates => new Climates(['en', 'fr', 'de'], [$condition], [$season], [$zone]), 'conditions.clear.labels: no label in "fr", "de"'];
        yield 'missing season label' => [static fn (): Climates => new Climates(['en'], [$condition], [new Season('summer', ['fr' => 'été'])], [$zone]), 'seasons.summer.labels: no label in "en"'];
        yield 'missing zone label' => [static fn (): Climates => new Climates(['en'], [$condition], [$season], [new Zone('temperate', ['fr' => 'tempéré'], [new SeasonalClimate('summer', 10, 20, ['clear' => 1])])]), 'zones.temperate.labels: no label in "en"'];
        yield 'no condition' => [static fn (): Climates => new Climates(['en'], [], [$season], [$zone]), 'conditions: the climates declare 1 to 64 conditions'];
        yield 'no season' => [static fn (): Climates => new Climates(['en'], [$condition], [], [$zone]), 'seasons: the climates declare 1 to 64 seasons'];
        yield 'no zone' => [static fn (): Climates => new Climates(['en'], [$condition], [$season], []), 'zones: the climates declare 1 to 64 zones'];
        yield 'too many conditions' => [static fn (): Climates => new Climates(['en'], array_map(static fn (int $i): Condition => new Condition('c'.$i, ['en' => 'c']), range(1, 65)), [$season], [$zone]), 'conditions: the climates declare 1 to 64 conditions'];
        yield 'condition twice' => [static fn (): Climates => new Climates(['en'], [$condition, $condition], [$season], [$zone]), 'conditions.clear: an identifier is declared once'];
        yield 'unknown neighbour' => [static fn (): Climates => new Climates(['en'], [new Condition('clear', ['en' => 'clear'], 0, ['fog'])], [$season], [$zone]), 'conditions.clear.next_to: the condition "fog" is not declared'];
        yield 'unknown season' => [static fn (): Climates => new Climates(['en'], [$condition], [new Season('winter', ['en' => 'winter'])], [$zone]), 'zones.temperate.seasons.summer: the season "summer" is not declared'];
        yield 'unknown condition' => [static fn (): Climates => new Climates(['en'], [new Condition('fog', ['en' => 'fog'])], [$season], [$zone]), 'zones.temperate.seasons.summer.conditions: the condition "clear" is not declared'];
    }

    /**
     * @param Closure(): mixed $build
     * @param non-empty-string $message
     */
    #[DataProvider('invalid')]
    public function testInvalidClimatesNameTheirPlace(Closure $build, string $message): void
    {
        try {
            $build();
            self::fail('The climates are taken.');
        } catch (InvalidClimate $invalidClimate) {
            self::assertStringStartsWith($message, $invalidClimate->getMessage());
        }
    }

    public function testTheBoundsAreReached(): void
    {
        $locales = ['aa', 'ab', 'ac', 'ad', 'ae', 'af', 'ag', 'en'];
        $labels = array_fill_keys($locales, 'label');
        $conditions = array_map(static fn (int $i): Condition => new Condition('c'.$i, $labels), range(1, 64));
        $climates = new Climates($locales, $conditions, [new Season('s', $labels)], [new Zone('z', $labels, [new SeasonalClimate('s', 0, 1, ['c1' => 1])])]);

        self::assertCount(64, $climates->conditions());
        self::assertSame([64, 8], [Climates::DEFINITIONS, Climates::LOCALES]);
        self::assertSame([], $climates->neighbours('c1'));
    }

    public function testTheMovesOfTheWeatherTablesAreBounded(): void
    {
        $labels = ['en' => 'label'];
        $ids = array_map(static fn (int $i): string => 'c'.$i, range(1, 50));
        $linked = array_map(static fn (string $id): Condition => new Condition($id, $labels, 0, array_values(array_diff($ids, [$id]))), $ids);
        $zones = static fn (array $sizes): array => array_map(static fn (int $zone, int $size): Zone => new Zone('z'.$zone, $labels, [new SeasonalClimate('s', 0, 1, array_fill_keys(\array_slice($ids, 0, $size), 1))]), array_keys($sizes), $sizes);

        // Fifty conditions next to one another: a season with fifty of them holds fifty moves from each, 2 500 in all.
        self::assertCount(8, new Climates(['en'], $linked, [new Season('s', $labels)], $zones(array_fill(0, 8, 50)))->zones());
        self::assertSame(20_000, Climates::TRANSITIONS);

        $this->refused(['en'], $linked, $zones([...array_fill(0, 8, 50), 1]));

        // Conditions next to none: a condition the season does not bring moves to every condition it brings.
        $alone = array_map(static fn (int $i): Condition => new Condition('c'.$i, $labels), range(1, 64));
        $this->refused(['en'], $alone, $zones(array_fill(0, 19, 32)));
    }

    /**
     * @param list<string>    $locales
     * @param list<Condition> $conditions
     * @param list<Zone>      $zones
     */
    private function refused(array $locales, array $conditions, array $zones): void
    {
        try {
            new Climates($locales, $conditions, [new Season('s', ['en' => 'label'])], $zones);
            self::fail('The climates are taken.');
        } catch (InvalidClimate $invalidClimate) {
            self::assertSame('zones: the weather tables of the climates hold at most 20000 moves between conditions, every zone and season together', $invalidClimate->getMessage());
        }
    }
}
