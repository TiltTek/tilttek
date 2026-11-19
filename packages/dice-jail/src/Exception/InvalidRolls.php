<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Exception;

use InvalidArgumentException;

/**
 * Rolls or faces the statistics cannot judge: a face the die does not have, too many rolls, a die of one face, or
 * probabilities that are estimated rather than exact.
 */
final class InvalidRolls extends InvalidArgumentException implements DiceJailException
{
    public static function unexpectedFace(int $face): self
    {
        return new self(\sprintf('The rolls hold a %d, a face the die cannot show: they belong to another die.', $face));
    }

    public static function tooMany(int $limit): self
    {
        return new self(\sprintf('A tally holds at most %d rolls.', $limit));
    }

    public static function tooFewFaces(): self
    {
        return new self('A die judged by the statistics has at least two faces that can come up.');
    }

    public static function tooManyFaces(int $limit): self
    {
        return new self(\sprintf('A die judged by the statistics has at most %d faces.', $limit));
    }

    public static function emptyRange(int $lowest, int $highest): self
    {
        return new self(\sprintf('The faces from %d to %d hold no face.', $lowest, $highest));
    }

    public static function estimated(): self
    {
        return new self('The distribution is estimated from samples: a goodness-of-fit test needs exact probabilities.');
    }

    public static function truncated(): self
    {
        return new self('The distribution cuts its explosions at a depth: a goodness-of-fit test needs every outcome.');
    }

    public static function tooRare(): self
    {
        return new self('A face of the die is so rare that no reasonable number of rolls could judge it.');
    }
}
