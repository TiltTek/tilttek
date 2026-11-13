<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Exception;

use DomainException;

/**
 * A move the state of the game does not allow, or made for a team named by an email address: a puzzle the scenario does not hold, still locked, already solved,
 * already open, or without a hint left. Nothing changes when one is refused.
 */
final class PuzzleRefused extends DomainException implements PuzzleBuilderException
{
    public static function emailTeam(): self
    {
        return new self('A team is named by a stable identifier, never an email address.');
    }

    public static function unknown(string $puzzle): self
    {
        return new self(\sprintf('The scenario holds no puzzle "%s".', $puzzle));
    }

    public static function locked(string $puzzle): self
    {
        return new self(\sprintf('The puzzle "%s" is still locked.', $puzzle));
    }

    public static function solved(string $puzzle): self
    {
        return new self(\sprintf('The puzzle "%s" is solved already.', $puzzle));
    }

    public static function noHintLeft(string $puzzle): self
    {
        return new self(\sprintf('The puzzle "%s" has no hint left to reveal.', $puzzle));
    }

    public static function notManual(string $puzzle): self
    {
        return new self(\sprintf('The puzzle "%s" opens by its unlock, not by the host.', $puzzle));
    }

    public static function opened(string $puzzle): self
    {
        return new self(\sprintf('The puzzle "%s" is open already.', $puzzle));
    }
}
