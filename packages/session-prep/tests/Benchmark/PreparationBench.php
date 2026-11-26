<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Tests\Benchmark;

use Tilttek\SessionPrep\ChecklistItem;
use Tilttek\SessionPrep\Clue;
use Tilttek\SessionPrep\NonPlayerCharacter;
use Tilttek\SessionPrep\Note;
use Tilttek\SessionPrep\Place;
use Tilttek\SessionPrep\Preparation;
use Tilttek\SessionPrep\Scene;
use Tilttek\SessionPrep\Secret;
use Tilttek\SessionPrep\SessionPreparer;
use Tilttek\SessionPrep\Store\InMemoryPreparationStore;

/**
 * What a game master does at the table with the largest preparation the library takes: a secret revealed and hidden
 * again, each change checking the whole preparation anew; and what was left over carried into the next one.
 */
final class PreparationBench
{
    private readonly SessionPreparer $preparer;

    private readonly Preparation $largest;

    private int $next = 0;

    public function __construct()
    {
        $keys = static fn (string $prefix, int $count): array => array_map(static fn (int $index): string => $prefix.$index, range(1, $count));
        $text = str_repeat('Fog over the harbour. ', 40);
        $characters = $keys('n', Preparation::MAX_CHARACTERS);
        $places = $keys('p', Preparation::MAX_PLACES);

        $this->largest = new Preparation(
            'prep-1',
            'campaign-1',
            'member-1',
            'The drowned bell',
            'session-1',
            array_map(static fn (string $key): Scene => new Scene($key, 'Scene '.$key, $text, \array_slice($characters, 0, Scene::MAX_CHARACTERS), \array_slice($places, 0, Scene::MAX_PLACES)), $keys('s', Preparation::MAX_SCENES)),
            array_map(static fn (string $key): Secret => new Secret($key, 'Secret '.$key, array_map(static fn (string $clue): Clue => new Clue($clue, 'A clue'), $keys('c', Secret::MAX_CLUES))), $keys('x', Preparation::MAX_SECRETS)),
            array_map(static fn (string $key): NonPlayerCharacter => new NonPlayerCharacter($key, 'Character '.$key, 'Wants the ledger', $text), $characters),
            array_map(static fn (string $key): Place => new Place($key, 'Place '.$key, $text), $places),
            array_map(static fn (string $key): Note => new Note($key, $text), $keys('o', Preparation::MAX_NOTES)),
            array_map(static fn (string $key): ChecklistItem => new ChecklistItem($key, 'Step '.$key), $keys('i', Preparation::MAX_CHECKLIST)),
        );
        $this->preparer = new SessionPreparer(new InMemoryPreparationStore());
        $this->preparer->create($this->largest);
    }

    public function benchMarkASecret(): int
    {
        $this->preparer->markSecret('prep-1', 'x25', true, 'member-1');

        return $this->preparer->markSecret('prep-1', 'x25', false, 'member-1')->version;
    }

    /**
     * @Revs(10)
     *
     * @Iterations(3)
     */
    public function benchCarryOver(): int
    {
        $id = 'next-'.++$this->next;
        $this->preparer->create(new Preparation($id, 'campaign-1', 'member-1', 'Next'));

        return \count($this->preparer->carryOver('prep-1', $id, 'member-1')->scenes);
    }

    /**
     * @Revs(10)
     *
     * @Iterations(3)
     */
    public function benchCheckTheLargest(): int
    {
        return $this->largest->withTitle('Renamed')->version;
    }
}
