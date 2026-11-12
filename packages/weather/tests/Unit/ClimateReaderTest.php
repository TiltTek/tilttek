<?php

declare(strict_types=1);

namespace Tilttek\Weather\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Weather\ClimateReader;
use Tilttek\Weather\Exception\InvalidClimate;

#[CoversClass(ClimateReader::class)]
final class ClimateReaderTest extends TestCase
{
    private const string DOCUMENT = <<<'YAML'
        locales: [en, fr]
        conditions:
          clear: { labels: { en: clear skies, fr: ciel dégagé }, temperature: 2, next_to: [fair] }
          fair: { labels: { en: fair, fr: beau temps } }
        seasons:
          summer: { labels: { en: summer, fr: été } }
          winter: { labels: { en: winter, fr: hiver } }
        zones:
          temperate:
            labels: { en: temperate, fr: tempéré }
            persistence: 4
            change: 1d3-2
            seasons:
              summer: { low: 14, high: 30, conditions: { clear: 5, fair: 2 } }
              winter: { low: -8, high: 6, conditions: { fair: 1 } }
          plain:
            labels: { en: plain, fr: plaine }
            seasons:
              summer: { low: 0, high: 1, conditions: { clear: 1 } }
        YAML;

    public function testADocumentGivesItsClimates(): void
    {
        $climates = new ClimateReader()->read(self::DOCUMENT);

        self::assertSame(['en', 'fr'], $climates->locales);
        self::assertSame(['clear', 'fair'], array_keys($climates->conditions()));
        self::assertSame(['en' => 'clear skies', 'fr' => 'ciel dégagé'], $climates->condition('clear')->labels);
        self::assertSame(2, $climates->condition('clear')->temperature);
        self::assertSame(0, $climates->condition('fair')->temperature);
        self::assertSame(['fair'], $climates->condition('clear')->neighbours);
        self::assertSame([], $climates->condition('fair')->neighbours);
        self::assertSame(['summer', 'winter'], array_keys($climates->seasons()));
        self::assertSame(['en' => 'winter', 'fr' => 'hiver'], $climates->season('winter')->labels);

        $zone = $climates->zone('temperate');

        self::assertSame([4, '1d3-2'], [$zone->persistence, $zone->change]);
        self::assertSame(['summer', 'winter'], array_keys($zone->seasons));
        self::assertSame([14, 30, ['clear' => 5, 'fair' => 2]], [$zone->seasons['summer']->low, $zone->seasons['summer']->high, $zone->seasons['summer']->conditions]);
        self::assertSame([3, '1d5-3'], [$climates->zone('plain')->persistence, $climates->zone('plain')->change]);
    }

    public function testAFileIsRead(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'climates');
        self::assertIsString($file);

        try {
            file_put_contents($file, self::DOCUMENT);
            self::assertSame(['temperate', 'plain'], array_keys(new ClimateReader()->file($file)->zones()));
        } finally {
            unlink($file);
        }

        $this->expectExceptionObject(InvalidClimate::at($file, 'the file cannot be read'));

        new ClimateReader()->file($file);
    }

    public function testADirectoryIsNotAFile(): void
    {
        $this->expectExceptionObject(InvalidClimate::at(__DIR__, 'the file cannot be read'));

        new ClimateReader()->file(__DIR__);
    }

    /**
     * @return iterable<string, array{string, non-empty-string}>
     */
    public static function invalid(): iterable
    {
        yield 'not YAML' => ['zones: [', 'climates: '];
        yield 'not a map' => ['- a', 'document: a map of names to values is expected'];
        yield 'empty' => ['', 'document: a map of names to values is expected'];
        yield 'unknown key' => [self::DOCUMENT."\nclimates: {}\nmore: 1", 'climates, more: not a key of climates: "locales", "conditions", "seasons" or "zones"'];
        yield 'no conditions' => ['locales: [en]', 'conditions: a map of names to values is expected'];
        yield 'condition not a map' => [str_replace('fair: { labels: { en: fair, fr: beau temps } }', 'fair: fine', self::DOCUMENT), 'conditions.fair: a map of names to values is expected'];
        yield 'condition key' => [str_replace('next_to: [fair]', 'next_to: [fair], colour: grey', self::DOCUMENT), 'conditions.clear.colour: not a key here: "labels", "temperature", "next_to"'];
        yield 'condition labels' => [str_replace('fair: { labels: { en: fair, fr: beau temps } }', 'fair: { temperature: 1 }', self::DOCUMENT), 'conditions.fair.labels: a map of names to values is expected'];
        yield 'shift' => [str_replace('temperature: 2', 'temperature: warm', self::DOCUMENT), 'conditions.clear.temperature: a whole number is expected'];
        yield 'neighbours' => [str_replace('next_to: [fair]', 'next_to: fair', self::DOCUMENT), 'conditions.clear.next_to: a list of texts is expected'];
        yield 'neighbour' => [str_replace('next_to: [fair]', 'next_to: [fair, 3]', self::DOCUMENT), 'conditions.clear.next_to: a list of texts is expected'];
        yield 'neighbours map' => [str_replace('next_to: [fair]', 'next_to: { a: fair }', self::DOCUMENT), 'conditions.clear.next_to: a list of texts is expected'];
        yield 'season key' => [str_replace('summer: { labels: { en: summer, fr: été } }', 'summer: { labels: { en: summer, fr: été }, months: 3 }', self::DOCUMENT), 'seasons.summer.months: not a key here: "labels"'];
        yield 'season' => [str_replace('summer: { labels: { en: summer, fr: été } }', 'summer: hot', self::DOCUMENT), 'seasons.summer: a map of names to values is expected'];
        yield 'season labels' => [str_replace('summer: { labels: { en: summer, fr: été } }', 'summer: { labels: [summer] }', self::DOCUMENT), 'seasons.summer.labels: a map of names to values is expected'];
        yield 'zone' => [str_replace("  plain:\n", "  plain: flat\n  other:\n", self::DOCUMENT), 'zones.plain: a map of names to values is expected'];
        yield 'zone key' => [str_replace('persistence: 4', "persistence: 4\n    rivers: 2", self::DOCUMENT), 'zones.temperate.rivers: not a key here: "labels", "persistence", "change", "seasons"'];
        yield 'zone labels' => [str_replace('labels: { en: temperate, fr: tempéré }', 'labels: temperate', self::DOCUMENT), 'zones.temperate.labels: a map of names to values is expected'];
        yield 'persistence' => [str_replace('persistence: 4', 'persistence: high', self::DOCUMENT), 'zones.temperate.persistence: a whole number is expected'];
        yield 'change' => [str_replace('change: 1d3-2', 'change: 3', self::DOCUMENT), 'zones.temperate.change: the change of the temperature is a roll of tilttek/dice, such as "1d5-3"'];
        yield 'zone seasons' => [str_replace("    labels: { en: plain, fr: plaine }\n    seasons:\n      summer: { low: 0, high: 1, conditions: { clear: 1 } }", '    labels: { en: plain, fr: plaine }', self::DOCUMENT), 'zones.plain.seasons: a map of names to values is expected'];
        yield 'seasonal climate' => [str_replace('summer: { low: 0, high: 1, conditions: { clear: 1 } }', 'summer: mild', self::DOCUMENT), 'zones.plain.seasons.summer: a map of names to values is expected'];
        yield 'seasonal key' => [str_replace('summer: { low: 0, high: 1, conditions: { clear: 1 } }', 'summer: { low: 0, high: 1, wind: 2, conditions: { clear: 1 } }', self::DOCUMENT), 'zones.plain.seasons.summer.wind: not a key here: "low", "high", "conditions"'];
        yield 'low' => [str_replace('low: 0', 'low: cold', self::DOCUMENT), 'zones.plain.seasons.summer.low: a whole number is expected'];
        yield 'high' => [str_replace('high: 1,', 'high: 1.5,', self::DOCUMENT), 'zones.plain.seasons.summer.high: a whole number is expected'];
        yield 'weights' => [str_replace('conditions: { clear: 1 }', 'conditions: [clear]', self::DOCUMENT), 'zones.plain.seasons.summer.conditions: a map of names to values is expected'];
        yield 'seasonal place' => [str_replace('high: 1,', 'high: -1,', self::DOCUMENT), 'zones.plain.seasons.summer.high: -1 is not a whole number from 0 to 80'];
        yield 'locales' => [str_replace('locales: [en, fr]', 'locales: en', self::DOCUMENT), 'locales: a list of texts is expected'];
        yield 'no locales' => [str_replace('locales: [en, fr]', '', self::DOCUMENT), 'locales: a list of texts is expected'];
        yield 'object' => ["locales: !php/object 'O:8:\"stdClass\":0:{}'", 'climates: '];
    }

    /**
     * @param non-empty-string $message
     */
    #[DataProvider('invalid')]
    public function testAnInvalidDocumentNamesItsPlace(string $yaml, string $message): void
    {
        try {
            new ClimateReader()->read($yaml);
            self::fail('The document is taken.');
        } catch (InvalidClimate $invalidClimate) {
            self::assertStringStartsWith($message, $invalidClimate->getMessage());
        }
    }

    public function testALongFileIsRefusedForItsLength(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'climates');
        self::assertIsString($file);

        try {
            file_put_contents($file, str_repeat('#', ClimateReader::LENGTH + 1));
            new ClimateReader()->file($file);
            self::fail('A long file is read.');
        } catch (InvalidClimate $invalidClimate) {
            self::assertSame($file.': a document holds at most 1048576 bytes', $invalidClimate->getMessage());
        } finally {
            unlink($file);
        }
    }

    public function testAnArrayIsReadAsADocument(): void
    {
        $reader = new ClimateReader();

        try {
            $reader->climates([0 => 'x']);
            self::fail('A key that is not declared is taken.');
        } catch (InvalidClimate $invalidClimate) {
            self::assertSame('0: not a key of climates: "locales", "conditions", "seasons" or "zones"', $invalidClimate->getMessage());
        }

        self::assertSame(['en'], $reader->climates([
            'locales' => ['en'],
            'conditions' => ['clear' => ['labels' => ['en' => 'clear']]],
            'seasons' => ['summer' => ['labels' => ['en' => 'summer']]],
            'zones' => ['plain' => ['labels' => ['en' => 'plain'], 'seasons' => ['summer' => ['low' => 0, 'high' => 1, 'conditions' => ['clear' => 1]]]]],
        ])->locales);
    }

    public function testADocumentIsBounded(): void
    {
        self::assertSame(1_048_576, ClimateReader::LENGTH);

        try {
            new ClimateReader()->read(str_repeat('#', ClimateReader::LENGTH + 1), 'big.yaml');
            self::fail('A long document is read.');
        } catch (InvalidClimate $invalidClimate) {
            self::assertSame('big.yaml: a document holds at most 1048576 bytes', $invalidClimate->getMessage());
        }

        $this->expectExceptionObject(InvalidClimate::at('document', 'a map of names to values is expected'));

        new ClimateReader()->read(str_repeat('#', ClimateReader::LENGTH));
    }
}
