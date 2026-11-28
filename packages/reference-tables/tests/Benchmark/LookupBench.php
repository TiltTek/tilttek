<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Tests\Benchmark;

use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Component\Yaml\Yaml;
use Tilttek\ReferenceTables\Format\Syntax;
use Tilttek\ReferenceTables\Format\TableReader;
use Tilttek\ReferenceTables\Rolling\TableRoller;
use Tilttek\ReferenceTables\Search\TableSearch;
use Tilttek\ReferenceTables\TableCatalogue;

/**
 * What a game master's screen does during play: look a row up by range in a table of 1 000 rows and by key, roll a
 * table, search 50 tables of 100 rows, and read those tables from YAML when a cache is rebuilt.
 */
final readonly class LookupBench
{
    private string $yaml;

    private TableCatalogue $catalogue;

    private Randomizer $randomizer;

    public function __construct()
    {
        $tables = array_map(static fn (int $table): array => [
            'key' => 't'.$table,
            'names' => ['en' => 'Table '.$table, 'fr' => 'Table '.$table],
            'die' => '1d100',
            'columns' => [['key' => 'result', 'names' => 'Result'], ['key' => 'value', 'names' => 'Value', 'kind' => 'number']],
            'rows' => array_map(static fn (int $row): array => ['range' => (string) $row, 'result' => ['en' => 'Result '.$row, 'fr' => 'Résultat '.$row], 'value' => $row], range(1, 100)),
        ], range(1, 50));
        $tables[] = [
            'key' => 'large',
            'names' => 'Large',
            'die' => '1d1000',
            'columns' => [['key' => 'result', 'names' => 'Result']],
            'rows' => array_map(static fn (int $row): array => ['range' => (string) $row, 'result' => 'Result '.$row], range(1, 1000)),
        ];
        $tables[] = [
            'key' => 'keyed',
            'names' => 'Keyed',
            'lookup' => 'key',
            'columns' => [['key' => 'result', 'names' => 'Result']],
            'rows' => array_map(static fn (int $row): array => ['key' => 'k'.$row, 'result' => 'Result '.$row], range(1, 1000)),
        ];
        $document = ['format' => TableReader::FORMAT, 'source' => ['title' => 'Bench', 'licence' => 'owned'], 'tables' => $tables];
        $this->yaml = Yaml::dump($document, 6, 2);
        $this->catalogue = new TableReader(16 * 1024 * 1024)->catalogue($document);
        $this->randomizer = new Randomizer(new Mt19937(1));
    }

    public function benchLookUpByRange(): string
    {
        return $this->catalogue->get('large')->lookup(777)->display('result');
    }

    public function benchLookUpByKey(): string
    {
        return $this->catalogue->get('keyed')->row('k777')->display('result');
    }

    public function benchRollATable(): int
    {
        return new TableRoller()->roll($this->catalogue->get('large'), $this->randomizer)->position;
    }

    /**
     * @Revs(10)
     *
     * @Iterations(3)
     */
    public function benchSearch(): int
    {
        return \count(new TableSearch()->search($this->catalogue, 'résultat 99', 'fr', 100));
    }

    /**
     * @Revs(3)
     *
     * @Iterations(3)
     */
    public function benchReadYaml(): int
    {
        return \count(new TableReader(16 * 1024 * 1024)->parse($this->yaml, Syntax::Yaml));
    }
}
