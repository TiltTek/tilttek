<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Difficulty;

use Tilttek\Encounter\Exception\InvalidEncounter;
use Tilttek\Encounter\Internal\Identifier;

/**
 * One band of difficulty the game system declares — `low`, `moderate`, `deadly` — and the cost an encounter reaches it at
 * for the characters assessed.
 */
final readonly class Band
{
    /**
     * @param string $key       the band's key, as the system names it
     * @param int    $threshold the cost from which the encounter is of this band, 0 or more
     *
     * @throws InvalidEncounter when the key is not an identifier or the threshold is below 0
     */
    public function __construct(public string $key, public int $threshold)
    {
        Identifier::check($key, 'a band of difficulty');
        if ($threshold < 0) {
            throw InvalidEncounter::because(\sprintf('The threshold of the band %s is 0 or more.', $key));
        }
    }
}
