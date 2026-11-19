<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Exception;

use DomainException;

/**
 * A step the jail does not allow: a die jailed twice, a die released while free, a sentence that ends before it
 * starts, or a jail full.
 */
final class JailRefused extends DomainException implements DiceJailException
{
    public static function alreadyJailed(string $die): self
    {
        return new self(\sprintf('The die %s is in jail already.', $die));
    }

    public static function notJailed(string $die): self
    {
        return new self(\sprintf('The die %s is not in jail.', $die));
    }

    public static function sentenceInThePast(): self
    {
        return new self('A sentence ends after the moment the die goes to jail.');
    }

    public static function full(int $limit): self
    {
        return new self(\sprintf('A keeper holds at most %d dice in jail at once.', $limit));
    }
}
