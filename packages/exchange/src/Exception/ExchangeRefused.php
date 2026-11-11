<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Exception;

use DomainException;
use Tilttek\Exchange\Refusal;

/**
 * An exchange the rules of the application do not allow, with the reason, for the application to tell the member.
 */
final class ExchangeRefused extends DomainException implements ExchangeException
{
    private function __construct(
        public readonly Refusal $refusal,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function because(Refusal $refusal, string $detail): self
    {
        return new self($refusal, $detail);
    }
}
