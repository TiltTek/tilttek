<?php

declare(strict_types=1);

namespace Tilttek\Raffle;

use Tilttek\Raffle\Exception\InvalidRaffle;

/**
 * The checks and the canonical JSON the hashes of a raffle are computed over.
 *
 * @internal
 */
final class Encoding
{
    /** The label every hashed document starts with, so that a hash of this library is never one of another format. */
    public const string VERSION = 'tilttek-raffle/1';

    private function __construct()
    {
    }

    /**
     * The SHA-256, in hexadecimal, of the canonical JSON of the values: no escaped slash or Unicode character, so that
     * any tool that writes the same JSON computes the same hash.
     *
     * @param list<mixed> $values
     */
    public static function hash(array $values): string
    {
        return hash('sha256', self::json($values));
    }

    public static function json(mixed $value): string
    {
        return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    /**
     * @throws InvalidRaffle when the identifier is not 1 to 128 letters, digits, dots, colons, underscores or hyphens
     */
    public static function id(string $id): string
    {
        if (1 !== preg_match('/^[A-Za-z0-9._:-]{1,128}$/D', $id)) {
            throw InvalidRaffle::because('A raffle identifier is 1 to 128 letters, digits, dots, colons, underscores or hyphens.');
        }

        return $id;
    }

    /**
     * An entrant: an opaque reference the application gives, published in the proof, so never an address nor a name.
     *
     * @throws InvalidRaffle when the reference is not 1 to 128 letters, digits, dots, colons, underscores, tildes or
     *                       hyphens
     */
    public static function entrant(string $entrant): string
    {
        if (1 !== preg_match('/^[A-Za-z0-9._:~-]{1,128}$/D', $entrant)) {
            throw InvalidRaffle::because('An entrant is an opaque reference of 1 to 128 letters, digits, dots, colons, underscores, tildes or hyphens.');
        }

        return $entrant;
    }

    /**
     * @throws InvalidRaffle when the value is not `$length` lower-case hexadecimal digits
     */
    public static function hex(string $hex, string $what, int $length = 64): string
    {
        if (\strlen($hex) !== $length || 1 !== preg_match('/^[0-9a-f]+$/D', $hex)) {
            throw InvalidRaffle::because(\sprintf('The %s is %d lower-case hexadecimal digits.', $what, $length));
        }

        return $hex;
    }
}
