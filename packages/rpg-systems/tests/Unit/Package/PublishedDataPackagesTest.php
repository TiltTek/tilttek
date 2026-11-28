<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Tests\Unit\Package;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\RpgSystems\Attribution\AttributionRenderer;
use Tilttek\RpgSystems\DiceExpression;
use Tilttek\RpgSystems\Difficulty;
use Tilttek\RpgSystems\Format\SystemReader;
use Tilttek\RpgSystems\Import\Importer;
use Tilttek\RpgSystems\Import\ImportProfile;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Package\DataPackage;
use Tilttek\RpgSystems\Package\DataPackageReader;
use Tilttek\RpgSystems\Package\UpstreamFile;
use Tilttek\RpgSystems\RuleVariant;

/**
 * The data packages built from the open sources, read back with the strict reader. They live next to the packages of
 * the monorepo, in `data/`; a checkout of this package alone has none, and the tests are then skipped.
 */
#[CoversClass(DataPackage::class)]
#[CoversClass(DataPackageReader::class)]
#[CoversClass(UpstreamFile::class)]
#[CoversClass(SystemReader::class)]
final class PublishedDataPackagesTest extends TestCase
{
    private const string DATA = __DIR__.'/../../../../../data';

    /**
     * @return iterable<string, array{string, string, string, Licence, non-empty-string, array<string, int>, list<string>, list<string>, list<string>}>
     */
    public static function packages(): iterable
    {
        yield 'SRD 5.1' => ['rpg-data-srd-5.1', 'srd-5.1', '5.1', Licence::CcBy40, 'This work includes material taken from the System Reference Document 5.1 (“SRD 5.1”) by Wizards of the Coast LLC', ['skill' => 18, 'condition' => 15, 'damage-type' => 13, 'magic-school' => 8], ['d20-roll', 'advantage', 'disadvantage'], ['encumbrance', 'skills-with-different-abilities'], ['check']];
        yield 'SRD 5.2.1' => ['rpg-data-srd-5.2', 'srd-5.2', '5.2.1', Licence::CcBy40, 'This work includes material from the System Reference Document 5.2.1 (“SRD 5.2.1”) by Wizards of the Coast LLC', ['skill' => 18, 'condition' => 15, 'damage-type' => 13, 'magic-school' => 8], ['d20-test', 'advantage', 'disadvantage'], [], ['check', 'encounter']];
        yield 'Fate Core' => ['rpg-data-fate-core', 'fate-core', '2013', Licence::CcBy30, 'This work is based on Fate Core System and Fate Accelerated Edition', ['skill' => 18, 'approach' => 6, 'ladder' => 11], ['fate-roll'], [], []];
        yield 'Pathfinder Second Edition' => ['rpg-data-pf2e', 'pf2e', '2023', Licence::Orc, 'This product is licensed under the ORC License located at the Library of Congress at TX 9-307-067', [], ['check', 'fortune', 'misfortune'], ['automatic-bonus-progression', 'dual-class', 'free-archetype', 'proficiency-without-level'], ['simple', 'level', 'encounter']];
    }

    /**
     * @param non-empty-string   $attribution the first words of the attribution statement
     * @param array<string, int> $terms       the number of terms of each category, in their order
     * @param list<string>       $dice        the keys of the dice expressions
     * @param list<string>       $variants    the keys of the rule variants
     * @param list<string>       $numbers     the keys of the difficulty numbers
     */
    #[DataProvider('packages')]
    public function testAPublishedPackageReadsBackStrictly(string $directory, string $system, string $version, Licence $licence, string $attribution, array $terms, array $dice, array $variants, array $numbers): void
    {
        $path = self::DATA.'/'.$directory;

        if (!is_dir($path)) {
            self::markTestSkipped('The data packages are kept in the monorepo, next to its packages.');
        }

        $reader = new DataPackageReader();
        $package = $reader->manifest($path);
        $loaded = $reader->load($path);

        self::assertSame(['tilttek/'.$directory, $system, $version], [$package->name, $package->system, $package->version]);
        self::assertSame([$system, $version], [$loaded->key, $loaded->version]);
        self::assertSame($licence, $loaded->provenance->licence);
        self::assertStringStartsWith($attribution, $loaded->provenance->attribution);
        self::assertTrue($loaded->provenance->equals($package->source));
        self::assertSame(array_keys($terms), $loaded->categories());
        self::assertSame(array_values($terms), array_map(static fn (string $category): int => \count($loaded->terms($category)), $loaded->categories()));
        self::assertSame($dice, array_map(static fn (DiceExpression $expression): string => $expression->key, $loaded->diceExpressions()));
        self::assertCount(Licence::CcBy40 === $licence ? 6 : 0, $loaded->attributes());
        self::assertSame($variants, array_map(static fn (RuleVariant $variant): string => $variant->key, $loaded->variants()));
        self::assertSame($numbers, array_map(static fn (Difficulty $difficulty): string => $difficulty->key, $loaded->difficulties()));
        self::assertSame([], $loaded->partyRoles());
        self::assertEquals(new Importer()->import($this->profile($system), $this->extract($directory), $loaded->provenance)->difficulties(), $loaded->difficulties());

        // Every record carries the package's one source, and the notices the package ships are the renderer's.
        self::assertSame([$loaded->provenance], $loaded->provenances());
        self::assertSame(new AttributionRenderer()->text($loaded, all: true)."\n", file_get_contents($path.'/ATTRIBUTION.txt'));

        // The manifest names the publisher's file, by its address, digest and size; the books under the ORC licence
        // are no file.
        if (Licence::Orc === $licence) {
            self::assertSame([], $package->upstream);
        } else {
            self::assertCount(1, $package->upstream);
            self::assertSame('en', $package->upstream[0]->locale);
            self::assertStringStartsWith('https://', $package->upstream[0]->url->toString());
            self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $package->upstream[0]->sha256);
        }

        // The Composer package carries the licence of its material, by its SPDX identifier or, for the ORC licence,
        // which has none, by a reference of its own.
        $composer = json_decode((string) file_get_contents($path.'/composer.json'), true, 8, \JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        self::assertSame(['tilttek/'.$directory, Licence::Orc === $licence ? 'LicenseRef-ORC' : $licence->value, 'tilttek-rpg-data'], [$composer['name'] ?? null, $composer['license'] ?? null, $composer['type'] ?? null]);
        self::assertSame(['tilttek/rpg-systems' => '^0.1@dev'], $composer['require'] ?? null);

        // The package is split to its own repository with the files every split package shows.
        foreach (['README.md', 'LICENSE', 'CHANGELOG.md', 'CONTRIBUTING.md', 'SECURITY.md', 'CODE_OF_CONDUCT.md'] as $file) {
            self::assertFileExists($path.'/'.$file);
        }

        self::assertStringContainsString('## NEXT - YYYY-MM-DD', (string) file_get_contents($path.'/CHANGELOG.md'));
        self::assertStringContainsString('('.str_replace('-', ' ', $licence->value).')', (string) file_get_contents($path.'/CONTRIBUTING.md'));
    }

    /**
     * The profile a package is built with: its records the source states once for all are those of the package.
     */
    private function profile(string $system): ImportProfile
    {
        return match ($system) {
            'srd-5.1' => ImportProfile::srd51(),
            'srd-5.2' => ImportProfile::srd52()->withVersion('5.2.1'),
            'pf2e' => ImportProfile::pathfinder2e()->withVersion('2023'),
            default => ImportProfile::fateCore()->withVersion('2013'),
        };
    }

    /**
     * @return array<mixed>
     */
    private function extract(string $directory): array
    {
        $extract = json_decode((string) file_get_contents(self::DATA.'/extracts/'.substr($directory, \strlen('rpg-data-')).'.json'), true, 64, \JSON_THROW_ON_ERROR);
        self::assertIsArray($extract);
        self::assertIsArray($extract['tables'] ?? null);

        return $extract['tables'];
    }
}
