<?php

declare(strict_types=1);

namespace Tilttek\Activity\Projection;

use Tilttek\Activity\Exception\InvalidProjection;
use Tilttek\Activity\Exception\UnknownProjection;

/**
 * The projections of an application, by name, sorted by name.
 */
final readonly class Projections
{
    /**
     * @var array<string, Projection>
     */
    private array $projections;

    /**
     * @param iterable<Projection> $projections
     */
    public function __construct(iterable $projections = [])
    {
        $byName = [];

        foreach ($projections as $projection) {
            $name = $projection->name();

            if (1 !== preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $name)) {
                throw InvalidProjection::because(\sprintf('"%s" is not a projection name: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.', $name));
            }

            if (isset($byName[$name])) {
                throw InvalidProjection::because(\sprintf('Two projections are named "%s".', $name));
            }

            $byName[$name] = $projection;
        }

        ksort($byName, \SORT_STRING);
        $this->projections = $byName;
    }

    /**
     * @throws UnknownProjection
     */
    public function get(string $name): Projection
    {
        return $this->projections[$name] ?? throw UnknownProjection::named($name);
    }

    /**
     * @return list<Projection>
     */
    public function all(): array
    {
        return array_values($this->projections);
    }
}
