<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Contract;

use Tilttek\DiceJail\Exception\JailConflict;
use Tilttek\DiceJail\Jail\JailEvent;

/**
 * Where the history of each die is kept, event by event; the jail's state is read from it.
 */
interface JailStore
{
    /**
     * Appends the event at its position, the count of the die's events plus one.
     *
     * @throws JailConflict when the die's history holds another count of events: another writer came first
     */
    public function append(JailEvent $event): void;

    /**
     * The history of one die of the keeper, in order.
     *
     * @return list<JailEvent>
     */
    public function events(string $keeper, string $die): array;

    /**
     * The dice of the keeper that have a history, in the order they first went to jail.
     *
     * @return list<string>
     */
    public function dice(string $keeper): array;
}
