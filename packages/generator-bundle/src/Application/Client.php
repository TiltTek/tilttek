<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Application;

use SensitiveParameter;
use Tilttek\GeneratorBundle\Application\Contract\DrawRateLimiter;

/**
 * Another site or application allowed to draw from some generators of the API, with its token and its rate limit.
 */
final readonly class Client
{
    /**
     * @param list<string> $generators the generators open to the client; `*` opens them all
     */
    public function __construct(
        public string $id,
        #[SensitiveParameter]
        private string $token,
        public array $generators,
        public ?DrawRateLimiter $limiter = null,
    ) {
    }

    public function hasToken(#[SensitiveParameter] string $token): bool
    {
        return hash_equals($this->token, $token);
    }

    public function mayDraw(string $generator): bool
    {
        return \in_array('*', $this->generators, true) || \in_array($generator, $this->generators, true);
    }
}
