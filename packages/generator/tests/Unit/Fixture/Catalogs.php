<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Fixture;

use Random\Randomizer;
use Tilttek\Generator\Definition\Catalog;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Limits;
use Tilttek\Generator\Loading\Reader;
use Tilttek\Generator\Timer;

/**
 * Catalogs written as the arrays a YAML file decodes to, and generators built on them.
 */
final class Catalogs
{
    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $lists
     * @param array<string, mixed> $generators
     */
    public static function of(array $lists = [], array $generators = []): Catalog
    {
        return new Reader()->catalog(['test' => array_filter(['lists' => $lists, 'generators' => $generators])]);
    }

    /**
     * @param array<string, mixed> $lists
     * @param array<string, mixed> $generators
     */
    public static function generator(array $lists, array $generators, ?Randomizer $randomizer = null, Limits $limits = new Limits(), string $locale = 'en', ?Timer $timer = null): Generator
    {
        return new Generator(
            self::of($lists, $generators),
            $limits,
            $locale,
            randomizer: $randomizer ?? new Randomizer(),
            timer: $timer ?? new SteppingTimer(0.0),
        );
    }
}
