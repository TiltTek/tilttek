<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\RpgSystems\GameSystem;
use Tilttek\RpgSystems\Import\Importer;
use Tilttek\RpgSystems\Import\ImportProfile;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\RuleVariant;
use Tilttek\RpgSystems\Systems;
use Tilttek\RpgSystems\Translations;
use Tilttek\TableRules\Catalogue\SystemVariantCatalogue;
use Tilttek\TableRules\Exception\InvalidRules;
use Tilttek\TableRules\Store\InMemoryRulebookStore;
use Tilttek\TableRules\TableRules;

/**
 * The rule variants of the game systems of `tilttek/rpg-systems`, as the catalogue a table's versions choose from.
 */
#[CoversClass(SystemVariantCatalogue::class)]
final class SystemVariantCatalogueTest extends TestCase
{
    private Systems $systems;

    protected function setUp(): void
    {
        $source = new Provenance('Lanterns', Licence::Owned);
        $lanterns = new GameSystem('lanterns', Translations::of(['en' => 'Lanterns']), $source, variants: [
            new RuleVariant('long-night', Translations::of(['en' => 'The long night']), $source),
        ]);
        $srd = new Importer()->import(ImportProfile::srd51(), [], new Provenance('SRD 5.1', Licence::CcBy40, 'Material from the SRD 5.1, CC BY 4.0.'));
        $this->systems = new Systems($lanterns, $srd);
    }

    public function testAVariantIsOneItsSystemOffers(): void
    {
        $catalogue = new SystemVariantCatalogue($this->systems);

        self::assertTrue($catalogue->has('lanterns', 'long-night'));
        self::assertTrue($catalogue->has('srd-5.1', 'encumbrance'));
        self::assertTrue($catalogue->has('srd-5.1', 'skills-with-different-abilities'));
        self::assertFalse($catalogue->has('lanterns', 'encumbrance'));
        self::assertFalse($catalogue->has('pf2e', 'free-archetype'));
        self::assertFalse($catalogue->has(null, 'long-night'));
    }

    public function testTheTableChoosesOnlyTheVariantsOfItsSystem(): void
    {
        $rules = new TableRules(new InMemoryRulebookStore(), new MockClock(new DateTimeImmutable('2027-01-01 12:00:00', new DateTimeZone('UTC'))), variants: new SystemVariantCatalogue($this->systems));

        self::assertSame(['encumbrance'], $rules->publish('t-1', 'gm', [], ['encumbrance'], 'srd-5.1')->current?->variants);

        $this->expectException(InvalidRules::class);
        $this->expectExceptionMessageIsOrContains('The game system offers no variant long-night.');
        $rules->publish('t-2', 'gm', [], ['long-night'], 'srd-5.1');
    }
}
