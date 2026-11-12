<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Application;

use SensitiveParameter;
use Tilttek\GeneratorBundle\Application\Contract\DrawRateLimiter;
use Tilttek\GeneratorBundle\Application\Exception\AccessDenied;

/**
 * Who may draw from which generator of the API: the generators open to anyone, counted by address, and the clients
 * known by their token, each with the generators open to it and its rate limit. The lists never leave the site; only
 * draws do.
 */
final readonly class ApiAccess
{
    /**
     * @param list<string> $public
     * @param list<Client> $clients
     */
    public function __construct(
        private bool $enabled = false,
        private array $public = [],
        private array $clients = [],
        private ?DrawRateLimiter $publicLimiter = null,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @throws AccessDenied
     */
    public function grant(string $generator, #[SensitiveParameter] ?string $token, string $address): Grant
    {
        if (null === $token) {
            if (!\in_array($generator, $this->public, true)) {
                throw AccessDenied::tokenRequired($generator);
            }

            return new Grant(null, 'address:'.$address, $this->publicLimiter);
        }

        foreach ($this->clients as $client) {
            if ($client->hasToken($token)) {
                return $client->mayDraw($generator) ? new Grant($client->id, 'client:'.$client->id, $client->limiter) : throw AccessDenied::notOpen($generator);
            }
        }

        throw AccessDenied::unknownToken();
    }
}
