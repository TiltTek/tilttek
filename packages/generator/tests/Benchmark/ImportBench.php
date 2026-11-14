<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Benchmark;

use Generator as Provider;
use Tilttek\Generator\Import\PerchanceImporter;
use Tilttek\Generator\Import\TraceryImporter;

/**
 * An author brings a grammar or tables written for another tool: the import of 10 and 1000 symbols or sections, each
 * with ten rules or entries that reference others, is measured.
 *
 * @BeforeMethods("setUp")
 */
final class ImportBench
{
    private string $tracery = '{}';

    private string $perchance = '';

    /**
     * @param array{symbols: int} $params
     */
    public function setUp(array $params): void
    {
        $grammar = ['origin' => ['#[hero:#s0#]s1# and #s0.capitalize#']];
        $tables = "output\n  [s0] and {a|b^2} {1-6} 2d6\n";

        for ($i = 0; $i < $params['symbols']; ++$i) {
            $next = 's'.(($i + 1) % $params['symbols']);
            $grammar['s'.$i] = array_map(static fn (int $rule): string => 0 === $i % 2 ? 'word'.$rule : 'the #'.$next.'.a# of #hero#', range(1, 10));
            $tables .= 's'.$i."\n".implode('', array_map(static fn (int $entry): string => 0 === $i % 2 ? '  word'.$entry." ^2\n" : '  the ['.$next."] {x|y}\n", range(1, 10)));
        }

        $this->tracery = json_encode($grammar, \JSON_THROW_ON_ERROR);
        $this->perchance = $tables;
    }

    /**
     * @ParamProviders("sizes")
     *
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchTracery(): void
    {
        new TraceryImporter()->import($this->tracery);
    }

    /**
     * @ParamProviders("sizes")
     *
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchPerchance(): void
    {
        new PerchanceImporter()->import($this->perchance);
    }

    /**
     * @return Provider<string, array{symbols: int}>
     */
    public function sizes(): Provider
    {
        yield '10 symbols' => ['symbols' => 10];
        yield '1000 symbols' => ['symbols' => 1000];
    }
}
