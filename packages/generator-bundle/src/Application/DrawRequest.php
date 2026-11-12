<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Application;

use Tilttek\Generator\Draw;
use Tilttek\Generator\Exception\InvalidDefinition;
use Tilttek\GeneratorBundle\Application\Exception\InvalidDrawRequest;

/**
 * Reads a draw from the parameters of a request: `count`, a whole number from 1 to the API's maximum; `unique`, `1`,
 * `true`, `0` or `false`; `seed`, a text of 1 to 128 characters; `state`, the state a table with a memory left at its
 * last draw, as `state[weather]=rain&state[day]=3` — at most 32 names to texts of at most 1024 characters. Every text,
 * and the name of the generator, is UTF-8. The API keeps no state: the client sends back the one the last answer gave.
 */
final class DrawRequest
{
    private const int SEED_LENGTH = 128;

    private function __construct()
    {
    }

    /**
     * The name of a generator as the path gives it, when it is UTF-8: an answer that repeats it stays valid JSON.
     *
     * @throws InvalidDrawRequest
     */
    public static function generator(string $name): string
    {
        return mb_check_encoding($name, 'UTF-8') ? $name : throw InvalidDrawRequest::because('The name of the generator is UTF-8.');
    }

    /**
     * @param array<array-key, mixed> $parameters
     *
     * @throws InvalidDrawRequest
     */
    public static function from(array $parameters, int $maxCount): Draw
    {
        $count = $parameters['count'] ?? '1';
        $unique = $parameters['unique'] ?? 'false';
        $seed = $parameters['seed'] ?? null;

        $number = \is_string($count) && ctype_digit($count) ? (int) $count : 0;

        if ($number < 1 || $number > $maxCount) {
            throw InvalidDrawRequest::because(\sprintf('The count is a whole number from 1 to %d.', $maxCount));
        }

        if (!\in_array($unique, ['1', 'true', '0', 'false'], true)) {
            throw InvalidDrawRequest::because('Unique is 1, true, 0 or false.');
        }

        if (null !== $seed && (!\is_string($seed) || '' === $seed || !mb_check_encoding($seed, 'UTF-8') || mb_strlen($seed) > self::SEED_LENGTH)) {
            throw InvalidDrawRequest::because(\sprintf('The seed is a text of 1 to %d characters, in UTF-8.', self::SEED_LENGTH));
        }

        $state = $parameters['state'] ?? null;

        if (null !== $state && !\is_array($state)) {
            throw InvalidDrawRequest::because('The state is a map of names to texts, such as state[weather]=rain.');
        }

        try {
            return new Draw($number, \in_array($unique, ['1', 'true'], true), $seed, $state);
        } catch (InvalidDefinition $invalidDefinition) {
            throw InvalidDrawRequest::because(\sprintf('The state is a map of at most %d names to texts of at most %d characters, in UTF-8.', Draw::STATE_VALUES, Draw::STATE_LENGTH), $invalidDefinition);
        }
    }
}
