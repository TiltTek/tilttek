<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Exception;

use DomainException;

/**
 * A move the rules of the game refuse: a card for a closed game, a call in a game ticked by activity, a claim on
 * another player's card, a card that does not match its seed, or a game or a card the store does not know.
 */
final class BingoRefused extends DomainException implements BingoException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public static function unknownGame(string $game): self
    {
        return new self(\sprintf('No bingo game has the identifier %s.', $game));
    }

    public static function unknownCard(string $game, int $serial): self
    {
        return new self(\sprintf('The bingo game %s has no card %d.', $game, $serial));
    }

    public static function closed(string $game): self
    {
        return new self(\sprintf('The bingo game %s is closed.', $game));
    }
}
