<?php

declare(strict_types=1);

namespace Tilttek\MathTrade\Exception;

use InvalidArgumentException;

/**
 * A seed the solver refuses: empty, longer than 256 bytes, not UTF-8, or holding a control character. A seed is
 * published with the result, so it is text anyone can copy.
 */
final class InvalidSeed extends InvalidArgumentException implements MathTradeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
