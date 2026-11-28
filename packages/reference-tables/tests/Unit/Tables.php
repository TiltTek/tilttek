<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Tests\Unit;

use Tilttek\ReferenceTables\Cell;
use Tilttek\ReferenceTables\Column;
use Tilttek\ReferenceTables\ColumnKind;
use Tilttek\ReferenceTables\Lookup;
use Tilttek\ReferenceTables\Range;
use Tilttek\ReferenceTables\ReferenceTable;
use Tilttek\ReferenceTables\Row;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\Translations;

/**
 * The tables the tests build in code.
 */
final class Tables
{
    public static function source(string $title = 'Test'): Provenance
    {
        return new Provenance($title, Licence::Owned);
    }

    public static function text(string $en, ?string $fr = null): Translations
    {
        return new Translations(null === $fr ? ['en' => $en] : ['en' => $en, 'fr' => $fr]);
    }

    /**
     * A table looked up by range, its rows given as range text => result.
     *
     * @param array<array-key, string> $rows
     */
    public static function ranged(array $rows, ?string $die = '2d6', string $key = 'reaction', ?string $system = null, ?string $category = null): ReferenceTable
    {
        return new ReferenceTable(
            $key,
            self::text('Reaction', 'Réaction'),
            self::source(),
            [new Column('result', self::text('Result'))],
            Lookup::Range,
            array_map(static fn (int|string $range, string $result): Row => Row::ranged(Range::parse((string) $range), ['result' => Cell::text(self::text($result))]), array_keys($rows), $rows),
            $die,
            $system,
            $category,
        );
    }

    /**
     * A table looked up by key, its rows given as key => defence.
     *
     * @param array<string, int> $rows
     */
    public static function keyed(array $rows = ['padded' => 1, 'plate' => 5], string $key = 'armor'): ReferenceTable
    {
        return new ReferenceTable(
            $key,
            self::text('Armor'),
            self::source(),
            [new Column('defence', self::text('Defence'), ColumnKind::Number)],
            Lookup::Key,
            array_map(static fn (string $row, int $defence): Row => Row::keyed($row, ['defence' => Cell::number($defence)]), array_keys($rows), $rows),
        );
    }
}
