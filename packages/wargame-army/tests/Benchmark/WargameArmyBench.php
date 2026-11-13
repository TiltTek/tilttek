<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Tests\Benchmark;

use Tilttek\WargameArmy\ArmyList;
use Tilttek\WargameArmy\ArmyValidator;
use Tilttek\WargameArmy\Catalog\InMemoryCatalogueProvider;
use Tilttek\WargameArmy\Catalogue;
use Tilttek\WargameArmy\Constraint\PointsShare;
use Tilttek\WargameArmy\Constraint\Selector;
use Tilttek\WargameArmy\Constraint\UnitCount;
use Tilttek\WargameArmy\Detachment;
use Tilttek\WargameArmy\DetachmentType;
use Tilttek\WargameArmy\Export\HtmlExport;
use Tilttek\WargameArmy\Option;
use Tilttek\WargameArmy\OptionGroup;
use Tilttek\WargameArmy\Slot;
use Tilttek\WargameArmy\UnitEntry;
use Tilttek\WargameArmy\UnitSelection;
use Tilttek\WargameArmy\Validation;

/**
 * What an army list costs on a page: the largest list — 20 detachments of 200 units, each with 10 options — validated
 * against a catalogue of 2 000 units and 200 constraints, exported as HTML, and the catalogue read from JSON.
 */
final class WargameArmyBench
{
    private readonly ArmyValidator $validator;

    private readonly Catalogue $catalogue;

    private readonly ArmyList $list;

    private readonly string $json;

    /** The last validation, kept so that the measured call has an effect. */
    public ?Validation $validation = null;

    public function __construct()
    {
        $options = array_map(static fn (int $option): Option => new Option('o'.$option, 'Option '.$option, 1, 5), range(1, 10));
        $units = array_map(static fn (int $unit): UnitEntry => new UnitEntry('u'.$unit, 'Unit '.$unit, ['c'.($unit % 10)], 10, 1, 20, 2, [new OptionGroup('g', 'G', $options, 0, 50)]), range(1, 2000));
        $slots = array_map(static fn (int $category): Slot => new Slot('c'.$category, 0, 200), range(0, 9));
        $constraints = array_map(static fn (int $i): UnitCount|PointsShare => 0 === $i % 2 ? new UnitCount(Selector::unit('u'.$i), max: 50) : new PointsShare(Selector::category('c'.($i % 10)), maxPercent: 50), range(1, 200));

        $this->catalogue = new Catalogue('bench', 'Bench', '1', $units, [new DetachmentType('d', 'D', $slots)], 1, 20, $constraints);
        $this->validator = new ArmyValidator(new InMemoryCatalogueProvider($this->catalogue));

        $selection = static fn (int $unit): UnitSelection => new UnitSelection('u'.$unit, 10, array_fill_keys(array_map(static fn (int $option): string => 'o'.$option, range(1, 10)), 2));
        $detachment = new Detachment('d', null, array_map($selection, range(1, 200)));
        $this->list = new ArmyList('l', 'Bench', 'bench', '1', 1_000_000, array_fill(0, 20, $detachment));
        $this->json = $this->catalogue->toJson();
    }

    /**
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchValidate4000UnitsAnd200Constraints(): void
    {
        $this->validation = $this->validator->validate($this->list);
    }

    /**
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchExport4000UnitsAsHtml(): void
    {
        new HtmlExport()->render($this->list, $this->catalogue);
    }

    /**
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchReadACatalogueOf2000Units(): void
    {
        Catalogue::fromJson($this->json);
    }
}
