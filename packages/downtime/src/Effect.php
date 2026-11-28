<?php

declare(strict_types=1);

namespace Tilttek\Downtime;

use Tilttek\Downtime\Exception\InvalidDowntime;
use Tilttek\Downtime\Internal\Identifier;

/**
 * What an outcome gives or takes in one resource the system names, once the game master confirms it. The library
 * records it; the application applies it.
 */
final readonly class Effect
{
    /** The largest change of one effect, either way. */
    public const int MAX_DELTA = 1_000_000;

    /**
     * @param string $resource the key of the resource
     * @param int    $delta    a gain above 0, a loss below, never 0, at most `MAX_DELTA` either way
     *
     * @throws InvalidDowntime when the key is not one, or the change is 0 or out of its bounds
     */
    public function __construct(public string $resource, public int $delta)
    {
        Identifier::key($resource, 'a resource');

        if (0 === $delta || $delta < -self::MAX_DELTA || $delta > self::MAX_DELTA) {
            throw InvalidDowntime::because(\sprintf('The change of an effect is -%1$d to %1$d, never 0.', self::MAX_DELTA));
        }
    }
}
