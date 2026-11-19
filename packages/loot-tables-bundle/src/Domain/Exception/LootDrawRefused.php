<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Domain\Exception;

use DomainException;

/**
 * A draw that cannot be committed, revealed or entered: it was never committed, it was revealed already, its line or
 * its option is not one to draw here, or the dice entered do not match it.
 */
final class LootDrawRefused extends DomainException
{
    public static function notCommitted(string $order, string $line, string $option, int $unit): self
    {
        return new self(\sprintf('The draw of unit %d of option %s on line %s of order %s was never committed: commit the draws of an order when it is placed.', $unit, $option, $line, $order));
    }

    public static function alreadyRevealed(string $id): self
    {
        return new self(\sprintf('The draw %s was revealed already; a draw is made once.', $id));
    }

    public static function unknownDraw(string $id): self
    {
        return new self(\sprintf('No draw has the identifier %s.', $id));
    }

    public static function unknownLine(string $order, string $line): self
    {
        return new self(\sprintf('The order %s has no line %s.', $order, $line));
    }

    public static function notToDraw(string $line, string $option): self
    {
        return new self(\sprintf('The option %s of line %s is not a box left to draw.', $option, $line));
    }

    public static function unitCount(string $line, int $quantity, int $given): self
    {
        return new self(\sprintf('The line %s sells %d units; %d codes were given, one per unit.', $line, $quantity, $given));
    }

    public static function noEntries(string $line, string $option, string $reason): self
    {
        return new self(\sprintf('The option %s of line %s has no list to draw a text from: %s', $option, $line, $reason));
    }
}
