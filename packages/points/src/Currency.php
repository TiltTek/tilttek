<?php

declare(strict_types=1);

namespace Tilttek\Points;

use DateInterval;
use DateTimeImmutable;
use Exception;
use Tilttek\Points\Exception\InvalidPoints;

/**
 * A currency of points, named by the application — `points`, `crowns`, `stars` — and, when its points expire, their
 * lifetime as an ISO 8601 duration such as `P1Y` or `P6M`: each credit then expires that long after it was earned,
 * first in first out. The name a member reads is the application's translation of the code.
 */
final readonly class Currency
{
    public ?DateInterval $lifetime;

    public function __construct(
        public string $code,
        public ?string $lifetimeSpec = null,
    ) {
        self::checkCode($code);
        $this->lifetime = null === $lifetimeSpec ? null : $this->lifetime($lifetimeSpec);
    }

    /**
     * When a credit made at this moment expires; null when the points of this currency never expire.
     */
    public function expiresAt(DateTimeImmutable $creditedAt): ?DateTimeImmutable
    {
        return $this->lifetime instanceof DateInterval ? $creditedAt->add($this->lifetime) : null;
    }

    /**
     * @throws InvalidPoints when the code is not one: 1 to 32 lower-case letters, digits and "_", starting with a letter
     */
    public static function checkCode(string $code): string
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $code)) {
            throw InvalidPoints::because(\sprintf('"%s" is not a currency code: 1 to 32 lower-case letters, digits and "_", starting with a letter.', $code));
        }

        return $code;
    }

    private function lifetime(string $spec): DateInterval
    {
        try {
            $lifetime = new DateInterval($spec);
        } catch (Exception) {
            throw InvalidPoints::because(\sprintf('"%s" is not a lifetime: an ISO 8601 duration such as "P1Y" or "P6M".', $spec));
        }

        $origin = new DateTimeImmutable('@0');

        if ($origin->add($lifetime) <= $origin) {
            throw InvalidPoints::because(\sprintf('A lifetime is longer than nothing; "%s" is not.', $spec));
        }

        return $lifetime;
    }
}
