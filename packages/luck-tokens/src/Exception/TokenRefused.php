<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Exception;

use DomainException;
use Phil\Resource\ResourceReference;

/**
 * An operation the rules of the table do not allow. Nothing is written when one is refused.
 */
final class TokenRefused extends DomainException implements LuckTokensException
{
    public static function unknownTable(ResourceReference $table): self
    {
        return new self(\sprintf('The table %s has no rules for its tokens.', $table->toString()));
    }

    public static function unknownKind(string $kind): self
    {
        return new self(\sprintf('The table hands out no token "%s".', $kind));
    }

    public static function notGameMaster(ResourceReference $actor, string $what): self
    {
        return new self(\sprintf('Only a game master of the table %s; %s is not one.', $what, $actor->toString()));
    }

    public static function notHolder(ResourceReference $actor, ResourceReference $holder): self
    {
        return new self(\sprintf('%s does not hold the tokens of %s, and is not a game master of the table.', $actor->toString(), $holder->toString()));
    }

    public static function overCap(string $kind, ResourceReference $holder, int $balance, int $amount, int $cap): self
    {
        return new self(\sprintf('%s holds %d "%s" of %d at most; %d more would go over.', $holder->toString(), $balance, $kind, $cap, $amount));
    }

    public static function insufficient(string $kind, ResourceReference $holder, int $balance, int $amount): self
    {
        return new self(\sprintf('%s holds %d "%s"; %d cannot be spent nor given.', $holder->toString(), $balance, $kind, $amount));
    }

    public static function notTransferable(string $kind): self
    {
        return new self(\sprintf('The token "%s" is not passed from one holder to another.', $kind));
    }

    public static function sameHolder(ResourceReference $holder): self
    {
        return new self(\sprintf('%s cannot pass tokens to themselves.', $holder->toString()));
    }

    public static function operationReused(string $operation): self
    {
        return new self(\sprintf('The operation %s was done already, with other values; an operation identifier names one operation.', $operation));
    }

    public static function sessionStarted(string $session): self
    {
        return new self(\sprintf('The session %s was started already, by another operation.', $session));
    }

    public static function ledgerFull(ResourceReference $table, int $entries): self
    {
        return new self(\sprintf('The ledger of the table %s holds %d entries, the most it takes.', $table->toString(), $entries));
    }
}
