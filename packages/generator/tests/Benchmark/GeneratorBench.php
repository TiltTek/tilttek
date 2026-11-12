<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Benchmark;

use Generator as Provider;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Generator\Draw;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Limits;
use Tilttek\Generator\Loading\Reader;

/**
 * A generator draws results on each click of a game master: a template picks entries of lists, by weight or by tag,
 * and rolls dice. Draws of 1 and 100 results, unique or not, from lists of 10 and 10 000 entries are measured, and the
 * cost of building a generator on a catalog.
 *
 * @BeforeMethods("setUp")
 */
final class GeneratorBench
{
    private Generator $generator;

    /** @var array<string, mixed> */
    private array $catalog = [];

    private int $count = 1;

    private bool $unique = false;

    public function __construct()
    {
        $this->generator = new Generator(new Reader()->catalog(['bench' => ['lists' => ['l' => ['a']], 'generators' => ['g' => ['templates' => "{{ pick('l') }}"]]]]));
    }

    /**
     * @param array{entries: int, count: int, unique: bool} $params
     */
    public function setUp(array $params): void
    {
        $entries = [];

        for ($i = 0; $i < $params['entries']; ++$i) {
            $entries[] = ['text' => 'name'.$i, 'weight' => 1 + $i % 3, 'tags' => ['kind' => 0 === $i % 2 ? 'even' : 'odd']];
        }

        $this->catalog = ['bench' => [
            'lists' => [
                'names' => $entries,
                'titles' => ['the Bold', 'the Wise', 'the Quick'],
                'invented' => ['entries' => array_map(static fn (array $entry): array => ['text' => strtr($entry['text'], '0123456789', 'aeioubdfgk')] + $entry, $entries), 'markov' => ['allow_entries' => true]],
            ],
            'generators' => [
                'npc' => ['templates' => "{{ pick('names', where: {kind: 'even'}) }} {{ pick('titles') }} ({{ roll('3d6') }})"],
                'word' => ['templates' => "{{ pick('invented', where: {kind: 'even'}) }}"],
                'days' => ['state' => ['day' => ['start' => '1', 'next' => '{{ state.day + 1 }}'], 'title' => ['start' => "{{ pick('titles') }}", 'next' => "{{ pick('titles', not: state.title) }}"]], 'templates' => '{{ state.day }} {{ state.title }}'],
            ],
        ]];
        // The time limit is lifted: a loaded machine must not stop a measure.
        $this->generator = new Generator(new Reader()->catalog($this->catalog), new Limits(milliseconds: 60_000), randomizer: new Randomizer(new Xoshiro256StarStar(42)));
        $this->count = $params['count'];
        $this->unique = $params['unique'];
    }

    /**
     * @ParamProviders("draws")
     *
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchDraw(): void
    {
        $this->generator->draw('npc', new Draw($this->count, $this->unique));
    }

    /**
     * Words invented by a Markov chain learnt from the entries of a list.
     *
     * @ParamProviders("draws")
     *
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchInvent(): void
    {
        $this->generator->draw('word', new Draw($this->count, $this->unique));
    }

    /**
     * A generator with a state, each result going on from the one before.
     *
     * @ParamProviders("draws")
     *
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchRemember(): void
    {
        $this->generator->draw('days', new Draw($this->count, $this->unique, state: ['day' => '1']));
    }

    /**
     * @ParamProviders("catalogs")
     *
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchBuild(): void
    {
        new Generator(new Reader()->catalog($this->catalog));
    }

    /**
     * @return Provider<string, array{entries: int, count: int, unique: bool}>
     */
    public function draws(): Provider
    {
        foreach ([10, 10_000] as $entries) {
            yield \sprintf('%d entries, 1 result', $entries) => ['entries' => $entries, 'count' => 1, 'unique' => false];
            yield \sprintf('%d entries, 100 results', $entries) => ['entries' => $entries, 'count' => 100, 'unique' => false];
            yield \sprintf('%d entries, 100 unique', $entries) => ['entries' => $entries, 'count' => 100, 'unique' => true];
        }
    }

    /**
     * @return Provider<string, array{entries: int, count: int, unique: bool}>
     */
    public function catalogs(): Provider
    {
        yield '10 entries' => ['entries' => 10, 'count' => 1, 'unique' => false];
        yield '10 000 entries' => ['entries' => 10_000, 'count' => 1, 'unique' => false];
    }
}
