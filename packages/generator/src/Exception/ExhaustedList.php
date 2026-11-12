<?php

declare(strict_types=1);

namespace Tilttek\Generator\Exception;

use RuntimeException;

/**
 * A single pick that found no entry left once the filters and the exclusions were applied, or no new word a Markov
 * list could invent within its attempts.
 */
final class ExhaustedList extends RuntimeException implements GeneratorException
{
    public static function named(string $list): self
    {
        return new self(\sprintf('No entry of the list "%s" is left to pick.', $list));
    }

    public static function invented(string $list, int $attempts): self
    {
        return new self(\sprintf('The list "%s" invented no word that fits in %d attempts.', $list, $attempts));
    }
}
