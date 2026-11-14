<?php

declare(strict_types=1);

namespace Tilttek\Generator;

use Tilttek\Generator\Exception\InvalidDefinition;

/**
 * The bounds that stop a generator gone wild: the depth of calls from one generator to another, the picks, the steps,
 * the memory and the time spent on one result, the attempts at a unique result, the results of one draw, the cells of
 * one map, the time spent on a whole draw — its results and every attempt of a unique draw —, and the longest text a
 * `replace` or a `join` makes.
 */
final readonly class Limits
{
    /**
     * @param int $steps     the turns of every loop and the `set` assignments of one result
     * @param int $bytes     the longest text a `replace` or a `join` returns, in bytes of UTF-8
     * @param int $megabytes the memory one result may grow by, checked at each step
     */
    public function __construct(
        public int $depth = 8,
        public int $picks = 1000,
        public int $milliseconds = 250,
        public int $attempts = 10,
        public int $results = 100,
        public int $cells = 2500,
        public int $drawMilliseconds = 1000,
        public int $steps = 10_000,
        public int $bytes = 65_536,
        public int $megabytes = 16,
    ) {
        foreach (get_object_vars($this) as $name => $value) {
            if ($value < 1) {
                throw InvalidDefinition::at('limits', \sprintf('the limit "%s" is a whole number of at least 1', $name));
            }
        }
    }
}
