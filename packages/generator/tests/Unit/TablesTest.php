<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit;

use DirectoryIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Definition\ObjectBody;
use Tilttek\Generator\Draw;
use Tilttek\Generator\Exception\InvalidDefinition;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Limits;
use Tilttek\Generator\Loading\FileLoader;
use Tilttek\Generator\Tables;

#[CoversClass(Tables::class)]
final class TablesTest extends TestCase
{
    /**
     * The limits of a draw, but for its times: these tests read the system's clock, which coverage or a busy machine
     * slows down.
     */
    private function limits(): Limits
    {
        return new Limits(milliseconds: 60_000, drawMilliseconds: 60_000);
    }

    public function testTheTablesAreTheFilesOfTheirDirectory(): void
    {
        foreach (Tables::LOCALES as $locale) {
            $files = [];

            foreach (new DirectoryIterator(Tables::directory($locale)) as $file) {
                if ('yaml' === $file->getExtension()) {
                    $files[] = $file->getBasename('.yaml');
                }
            }

            sort($files);

            self::assertSame(Tables::NAMES, $files, $locale);
        }

        self::assertSame(['en', 'fr'], Tables::LOCALES);
        self::assertSame(\dirname(__DIR__, 2).'/tables/en', Tables::directory());
        self::assertSame(\dirname(__DIR__, 2).'/tables/fr', Tables::directory('fr'));
        self::assertSame(\dirname(__DIR__, 2).'/tables/en/shop.yaml', Tables::file('shop'));
        self::assertSame(\dirname(__DIR__, 2).'/tables/fr/shop.yaml', Tables::file('shop', 'fr'));
    }

    public function testAnUnknownTableIsAnError(): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('tables', 'no table is named "tavern"; the tables are "encounter", "fortune", "hook", "loot", "magic-item", "oracle", "rumour", "shop"'));

        Tables::file('tavern');
    }

    public function testAnUnknownLanguageIsAnError(): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('tables', 'the tables are not written in "de"; they are in "en", "fr"'));

        Tables::file('shop', 'de');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function tables(): iterable
    {
        foreach (Tables::LOCALES as $locale) {
            foreach (Tables::NAMES as $name) {
                yield $locale.' '.$name => [$name, $locale];
            }
        }
    }

    #[DataProvider('tables')]
    public function testEachTableStandsAloneAndDraws(string $name, string $locale): void
    {
        $generator = new Generator(new FileLoader()->catalog([Tables::file($name, $locale)]), $this->limits());
        $prefix = 'table.'.$name;

        self::assertNotSame([], $generator->catalog()->generators());

        foreach ([...array_keys($generator->catalog()->lists()), ...array_keys($generator->catalog()->generators())] as $definition) {
            self::assertTrue($definition === $prefix || str_starts_with($definition, $prefix.'.'), $definition);
        }

        foreach (array_keys($generator->catalog()->generators()) as $definition) {
            foreach (['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'] as $seed) {
                self::assertCount(3, $generator->draw($definition, new Draw(3, seed: $seed))->results);
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function names(): iterable
    {
        foreach (Tables::NAMES as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('names')]
    public function testTheFrenchTableHasTheShapeOfTheEnglishOne(string $name): void
    {
        $english = new FileLoader()->catalog([Tables::file($name)]);
        $french = new FileLoader()->catalog([Tables::file($name, 'fr')]);

        self::assertSame(array_keys($english->lists()), array_keys($french->lists()));
        self::assertSame(array_keys($english->generators()), array_keys($french->generators()));

        foreach ($english->generators() as $generatorName => $definition) {
            $translation = $french->generator($generatorName);

            self::assertSame('fr', $translation->locale, $generatorName);
            self::assertSame(array_keys($definition->state), array_keys($translation->state), $generatorName);
            self::assertSame(array_keys($definition->vars), array_keys($translation->vars), $generatorName);
            self::assertSame($definition->body::class, $translation->body::class, $generatorName);

            if ($definition->body instanceof ObjectBody && $translation->body instanceof ObjectBody) {
                self::assertSame(array_keys($definition->body->fields), array_keys($translation->body->fields), $generatorName);
            }
        }

        $englishDraw = new Generator($english, $this->limits())->draw('table.'.$name, new Draw(seed: 'shape'));
        $frenchDraw = new Generator($french, $this->limits())->draw('table.'.$name, new Draw(seed: 'shape'));

        self::assertSame(array_keys($englishDraw->state ?? []), array_keys($frenchDraw->state ?? []));
    }

    public function testTheOracleWeighsTheOddsAndTheChaos(): void
    {
        $generator = new Generator(new FileLoader()->catalog([Tables::file('oracle')]), $this->limits());
        $answers = static function (array $state) use ($generator): array {
            $answers = [];

            foreach ($generator->draw('table.oracle', new Draw(100, seed: 'odds', state: $state))->results as $result) {
                self::assertIsArray($result);
                self::assertIsString($result['answer']);
                $answers[$result['answer']] = ($answers[$result['answer']] ?? 0) + 1;
            }

            return $answers;
        };

        $likely = $answers(['odds' => 'certain', 'chaos' => '9']);
        $unlikely = $answers(['odds' => 'impossible', 'chaos' => '1']);

        self::assertGreaterThan(90, ($likely['Yes'] ?? 0) + ($likely['Yes, and…'] ?? 0));
        self::assertGreaterThan(90, ($unlikely['No'] ?? 0) + ($unlikely['No, and…'] ?? 0));
        self::assertSame(['chaos' => '5', 'odds' => 'even'], $generator->draw('table.oracle')->state);
    }

    public function testTheEncounterFollowsTheTerrainOfItsState(): void
    {
        $generator = new Generator(new FileLoader()->catalog([Tables::file('encounter')]), $this->limits());
        $results = implode(' ', array_map(static fn (mixed $result): string => \is_string($result) ? $result : '', $generator->draw('table.encounter', new Draw(20, seed: 'swamp', state: ['terrain' => 'underground']))->results));

        self::assertDoesNotMatchRegularExpression('/merchant|pilgrim|shepherd|leech/', $results);
    }

    public function testTheFrenchTablesSpeakFrench(): void
    {
        $encounters = new Generator(new FileLoader()->catalog([Tables::file('encounter', 'fr')]), $this->limits());
        $results = implode(' ', array_map(static fn (mixed $result): string => \is_string($result) ? $result : '', $encounters->draw('table.encounter', new Draw(20, seed: 'marais', state: ['terrain' => 'underground']))->results));

        self::assertDoesNotMatchRegularExpression('/marchand|pèlerin|bergère|sangsue/', $results);
        self::assertMatchesRegularExpression('/ : (il est|ils sont|elle est|elles sont) /', $results);

        $oracle = new Generator(new FileLoader()->catalog([Tables::file('oracle', 'fr')]), $this->limits());
        $answers = array_map(static fn (mixed $result): string => \is_array($result) && \is_string($result['answer']) ? $result['answer'] : '', $oracle->draw('table.oracle', new Draw(50, seed: 'oui', state: ['odds' => 'certain', 'chaos' => '9']))->results);

        self::assertContains('Oui', $answers);
        self::assertSame([], array_diff($answers, ['Oui', 'Oui, et…', 'Non', 'Non, et…']));

        $rumours = new Generator(new FileLoader()->catalog([Tables::file('rumour', 'fr')]), $this->limits());

        foreach ($rumours->draw('table.rumour', new Draw(10, seed: 'rumeur'))->results as $result) {
            self::assertIsArray($result);
            self::assertIsString($result['rumour']);
            self::assertStringStartsWith('On dit qu', $result['rumour']);
            self::assertStringNotContainsString('de le ', $result['rumour']);
            self::assertStringNotContainsString(' que un', $result['rumour']);
        }
    }
}
