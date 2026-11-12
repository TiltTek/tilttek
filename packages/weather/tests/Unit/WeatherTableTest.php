<?php

declare(strict_types=1);

namespace Tilttek\Weather\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Definition\ObjectBody;
use Tilttek\Generator\Draw;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Loading\FileLoader;
use Tilttek\Weather\Climates;
use Tilttek\Weather\Condition;
use Tilttek\Weather\Exception\InvalidClimate;
use Tilttek\Weather\Exception\UnknownClimate;
use Tilttek\Weather\Season;
use Tilttek\Weather\SeasonalClimate;
use Tilttek\Weather\WeatherTable;
use Tilttek\Weather\Zone;

#[CoversClass(WeatherTable::class)]
final class WeatherTableTest extends TestCase
{
    public function testAConditionStaysOrMovesToANeighbourTheSeasonBrings(): void
    {
        $lists = WeatherTable::document($this->climates(), 'fr', 'sky')['lists'];

        self::assertSame(['sky.condition', 'sky.season.plain', 'sky.climate.plain.summer', 'sky.start.plain.summer', 'sky.transition.plain.summer', 'sky.climate.plain.winter', 'sky.start.plain.winter', 'sky.transition.plain.winter'], array_keys($lists));
        self::assertSame([
            ['text' => 'clear', 'label' => 'dégagé', 'shift' => 3, 'tags' => ['id' => ['clear']]],
            ['text' => 'fair', 'label' => 'beau', 'shift' => 0, 'tags' => ['id' => ['fair']]],
            ['text' => 'rain', 'label' => 'pluie', 'shift' => -2, 'tags' => ['id' => ['rain']]],
            ['text' => 'snow', 'label' => 'neige', 'shift' => -4, 'tags' => ['id' => ['snow']]],
        ], $lists['sky.condition']);
        self::assertSame([['text' => 'summer']], $lists['sky.season.plain']);
        self::assertSame([['text' => 'summer', 'low' => 10, 'high' => 25, 'middle' => 17, 'change' => '1d3-2']], $lists['sky.climate.plain.summer']);
        self::assertSame([['text' => 'clear', 'weight' => 3], ['text' => 'fair', 'weight' => 2], ['text' => 'rain', 'weight' => 1]], $lists['sky.start.plain.summer']);
        self::assertSame([
            ['text' => 'clear', 'weight' => 12, 'tags' => ['from' => ['clear']]],
            ['text' => 'fair', 'weight' => 2, 'tags' => ['from' => ['clear']]],
            ['text' => 'fair', 'weight' => 8, 'tags' => ['from' => ['fair']]],
            ['text' => 'clear', 'weight' => 3, 'tags' => ['from' => ['fair']]],
            ['text' => 'rain', 'weight' => 1, 'tags' => ['from' => ['fair']]],
            ['text' => 'rain', 'weight' => 4, 'tags' => ['from' => ['rain']]],
            ['text' => 'fair', 'weight' => 2, 'tags' => ['from' => ['rain']]],
            ['text' => 'fair', 'weight' => 2, 'tags' => ['from' => ['snow']]],
            ['text' => 'rain', 'weight' => 1, 'tags' => ['from' => ['snow']]],
        ], $lists['sky.transition.plain.summer']);
        self::assertSame([
            ['text' => 'fair', 'weight' => 1, 'tags' => ['from' => ['clear']]],
            ['text' => 'fair', 'weight' => 4, 'tags' => ['from' => ['fair']]],
            ['text' => 'snow', 'weight' => 5, 'tags' => ['from' => ['fair']]],
            ['text' => 'fair', 'weight' => 1, 'tags' => ['from' => ['rain']]],
            ['text' => 'snow', 'weight' => 5, 'tags' => ['from' => ['rain']]],
            ['text' => 'snow', 'weight' => 20, 'tags' => ['from' => ['snow']]],
            ['text' => 'fair', 'weight' => 1, 'tags' => ['from' => ['snow']]],
        ], $lists['sky.transition.plain.winter']);
    }

    public function testAConditionWithoutANeighbourInTheSeasonMovesToAnyOfIt(): void
    {
        $climates = new Climates(['en'], [new Condition('a', ['en' => 'a'], 0, ['b']), new Condition('b', ['en' => 'b']), new Condition('c', ['en' => 'c'])], [new Season('s', ['en' => 's'])], [new Zone('z', ['en' => 'z'], [new SeasonalClimate('s', 0, 1, ['c' => 2, 'a' => 1])], 2)]);

        self::assertSame([
            ['text' => 'a', 'weight' => 2, 'tags' => ['from' => ['a']]],
            ['text' => 'a', 'weight' => 1, 'tags' => ['from' => ['b']]],
            ['text' => 'c', 'weight' => 4, 'tags' => ['from' => ['c']]],
        ], WeatherTable::document($climates)['lists']['weather.transition.z.s']);
    }

    public function testTheGeneratorKeepsTheZoneAndSeasonTheApplicationSets(): void
    {
        $generator = WeatherTable::document($this->climates())['generators']['weather'];

        self::assertIsArray($generator['state']);
        self::assertSame(['zone', 'season', 'condition', 'temperature'], array_keys($generator['state']));
        self::assertSame('plain', $generator['state']['zone']);
        $body = WeatherTable::catalog($this->climates())->generator('weather')->body;

        self::assertInstanceOf(ObjectBody::class, $body);
        self::assertSame(['condition', 'label', 'temperature'], array_keys($body->fields));
    }

    public function testTheCatalogDraws(): void
    {
        $generator = new Generator(WeatherTable::catalog($this->climates(), 'en', 'sky'));
        $result = $generator->draw('sky', new Draw(20, seed: 'x'));

        self::assertSame(['zone' => 'plain', 'season' => 'summer'], array_intersect_key($result->state ?? [], ['zone' => 1, 'season' => 1]));

        foreach ($result->results as $day) {
            self::assertIsArray($day);
            self::assertContains($day['condition'], ['clear', 'fair', 'rain']);
            self::assertContains($day['label'], ['clear', 'fair', 'rain']);
            self::assertIsString($day['temperature']);
            self::assertGreaterThanOrEqual(10, (int) $day['temperature']);
            self::assertLessThanOrEqual(25, (int) $day['temperature']);
        }
    }

    public function testATemperatureOutOfTheRangeComesBackByTwoDegreesADay(): void
    {
        $generator = new Generator(WeatherTable::catalog($this->climates()));
        $temperatures = array_map(static fn (mixed $day): mixed => \is_array($day) ? $day['temperature'] : null, $generator->draw('weather', new Draw(4, seed: 'cold', state: ['zone' => 'plain', 'season' => 'winter', 'condition' => 'snow', 'temperature' => '11']))->results);

        self::assertSame(['9', '7', '5'], [$temperatures[0], $temperatures[1], $temperatures[2]]);
        self::assertContains($temperatures[3], ['3', '4', '5']);
        self::assertSame(2, WeatherTable::RETURN);

        $warm = array_map(static fn (mixed $day): mixed => \is_array($day) ? $day['temperature'] : null, $generator->draw('weather', new Draw(3, seed: 'warm', state: ['zone' => 'plain', 'season' => 'summer', 'condition' => 'clear', 'temperature' => '6']))->results);

        self::assertSame(['8', '10'], [$warm[0], $warm[1]]);
    }

    public function testTheStartingTemperatureIsTheMiddleMovedByTheConditionWithinTheRange(): void
    {
        $climates = new Climates(
            ['en'],
            [new Condition('hot', ['en' => 'hot'], 20), new Condition('cold', ['en' => 'cold'], -20), new Condition('mild', ['en' => 'mild'], 1)],
            [new Season('s', ['en' => 's']), new Season('t', ['en' => 't']), new Season('u', ['en' => 'u'])],
            [new Zone('z', ['en' => 'z'], [new SeasonalClimate('s', 0, 4, ['hot' => 1]), new SeasonalClimate('t', 0, 4, ['cold' => 1]), new SeasonalClimate('u', 0, 11, ['mild' => 1])])],
        );
        $generator = new Generator(WeatherTable::catalog($climates));

        foreach (['s' => '4', 't' => '0', 'u' => '6'] as $season => $temperature) {
            self::assertSame($temperature, $generator->draw('weather', new Draw(state: ['zone' => 'z', 'season' => $season]))->state['temperature'] ?? null, $season);
        }
    }

    public function testTheShippedFilesAreWrittenFromTheShippedClimates(): void
    {
        self::assertSame(['en', 'fr'], WeatherTable::LOCALES);

        foreach (WeatherTable::LOCALES as $locale) {
            self::assertSame(\dirname(__DIR__, 2).'/generators/'.$locale, WeatherTable::directory($locale));
            self::assertSame(WeatherTable::yaml(Climates::default(), $locale), file_get_contents(WeatherTable::directory($locale).'/weather.yaml'), $locale);

            $generator = new Generator(new FileLoader()->catalog([WeatherTable::directory($locale)]));
            self::assertCount(5, $generator->draw('weather', new Draw(5, seed: 'file'))->results);
        }

        self::assertSame(\dirname(__DIR__, 2).'/generators/en', WeatherTable::directory());
        self::assertStringStartsWith("# The weather written from declared climates by tilttek/weather, in \"fr\": WeatherTable::yaml().\nlists:\n  weather.condition:\n    - { text: clear, label: 'ciel dégagé', shift: 2, tags: { id: [clear] } }\n", WeatherTable::yaml(Climates::default(), 'fr'));
        self::assertStringContainsString("\ngenerators:\n  weather:\n    state:\n      zone: temperate\n", WeatherTable::yaml(Climates::default()));

        $this->expectExceptionObject(UnknownClimate::of('language', 'de', ['en', 'fr']));

        WeatherTable::directory('de');
    }

    public function testTheLanguageAndTheNameAreChecked(): void
    {
        try {
            WeatherTable::document($this->climates(), 'de');
            self::fail('A language without labels is taken.');
        } catch (UnknownClimate $unknownClimate) {
            self::assertSame('The language "de" is not declared; the climates know "en", "fr".', $unknownClimate->getMessage());
        }

        $this->expectExceptionObject(InvalidClimate::at('bad name', 'bad name: "bad name.condition" is not a name: letters, digits, "_" and "-", in parts joined by dots'));

        WeatherTable::catalog($this->climates(), 'en', 'bad name');
    }

    private function climates(): Climates
    {
        return new Climates(
            ['en', 'fr'],
            [
                new Condition('clear', ['en' => 'clear', 'fr' => 'dégagé'], 3, ['fair']),
                new Condition('fair', ['en' => 'fair', 'fr' => 'beau'], 0, ['rain', 'snow']),
                new Condition('rain', ['en' => 'rain', 'fr' => 'pluie'], -2),
                new Condition('snow', ['en' => 'snow', 'fr' => 'neige'], -4, ['rain']),
            ],
            [new Season('summer', ['en' => 'summer', 'fr' => 'été']), new Season('winter', ['en' => 'winter', 'fr' => 'hiver'])],
            [new Zone('plain', ['en' => 'plain', 'fr' => 'plaine'], [new SeasonalClimate('summer', 10, 25, ['clear' => 3, 'fair' => 2, 'rain' => 1]), new SeasonalClimate('winter', -10, 5, ['fair' => 1, 'snow' => 5])], 4, '1d3-2')],
        );
    }
}
