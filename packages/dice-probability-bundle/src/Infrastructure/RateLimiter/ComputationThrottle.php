<?php

declare(strict_types=1);

namespace Tilttek\DiceProbabilityBundle\Infrastructure\RateLimiter;

use SensitiveParameter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Throwable;

/**
 * The rate limit of the computations a client asks the Live Component for, on `symfony/rate-limiter`: one limiter per
 * network, an IPv4 address on its own and an IPv6 address with the others of its /64, since one client is usually given
 * a whole /64.
 *
 * The key is a keyed digest of the network, never the address. **It fails closed**: a failure of the limiter or its
 * storage refuses the computation, since a computation without a bound is what the limit is there to prevent.
 */
final readonly class ComputationThrottle
{
    /** The network of a request without a client address: all such requests share one limit. */
    public const string UNKNOWN = 'unknown';

    public function __construct(
        private RateLimiterFactoryInterface $factory,
        private RequestStack $requests,
        #[SensitiveParameter]
        private string $secret,
    ) {
    }

    /**
     * Whether the client of the current request may have one more computation, counted when it may.
     */
    public function allows(): bool
    {
        $network = self::network($this->requests->getMainRequest()?->getClientIp());

        try {
            return $this->factory->create(hash_hmac('sha256', $network, $this->secret))->consume()->isAccepted();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The network an address is counted in: an IPv4 address, or one mapped into IPv6, as it is; an IPv6 address as its
     * /64, `2001:db8:1:2::/64`; `UNKNOWN` without an address, or with one that is not.
     */
    public static function network(?string $address): string
    {
        $bytes = null === $address ? false : inet_pton($address);

        if (false === $bytes) {
            return self::UNKNOWN;
        }

        if (16 === \strlen($bytes) && str_starts_with($bytes, str_repeat("\0", 10)."\xff\xff")) {
            $bytes = substr($bytes, 12);
        }

        if (4 === \strlen($bytes)) {
            return (string) inet_ntop($bytes);
        }

        return inet_ntop(substr($bytes, 0, 8).str_repeat("\0", 8)).'/64';
    }
}
