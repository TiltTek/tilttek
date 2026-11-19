<?php

declare(strict_types=1);

namespace Tilttek\Raffle;

/**
 * The draw from a seed, written so that any language computes it again: the entrants in byte order, then a partial
 * Fisher-Yates shuffle whose `i`-th step swaps entrant `i` with entrant `i + u`, `u` drawn uniformly below the number
 * of entrants left. `u` is the first 6 bytes, read as a big-endian whole number, of the SHA-256 of the 32 bytes of the
 * seed followed by `i` and an attempt counter, both as 4-byte big-endian numbers; a value at or above the largest
 * multiple of the bound below 2⁴⁸ is rejected and the next attempt drawn, so that no entrant is favoured.
 *
 * @internal
 */
final class Selection
{
    /** The bits of each value drawn. */
    public const int BITS = 48;

    private function __construct()
    {
    }

    /**
     * The first `$count` entrants of the shuffle, in the order drawn.
     *
     * @param string       $seed     the seed of the draw, 64 hexadecimal digits
     * @param list<string> $entrants the entrants, in byte order
     * @param int          $bits     the bits of each value drawn; 48 for every draw, fewer in tests only
     *
     * @return list<string>
     */
    public static function draw(string $seed, array $entrants, int $count, int $bits = self::BITS): array
    {
        $key = sodium_hex2bin($seed);
        $total = \count($entrants);

        for ($step = 0; $step < $count; ++$step) {
            $other = $step + self::below($key, $step, $total - $step, $bits);
            [$entrants[$step], $entrants[$other]] = [$entrants[$other], $entrants[$step]];
        }

        return \array_slice($entrants, 0, $count);
    }

    /**
     * A whole number drawn uniformly from 0 to `$bound - 1`.
     */
    private static function below(string $key, int $step, int $bound, int $bits): int
    {
        $limit = intdiv(1 << $bits, $bound) * $bound;

        for ($attempt = 0;; ++$attempt) {
            $value = self::value(hash('sha256', $key.pack('N2', $step, $attempt), true)) >> (self::BITS - $bits);

            if ($value < $limit) {
                return $value % $bound;
            }
        }
    }

    private static function value(string $digest): int
    {
        /** @var array{1: int} $value 'J' reads the first 8 bytes: two zeros, then the first 6 bytes of the digest */
        $value = unpack('J', "\0\0".$digest);

        return $value[1];
    }
}
