<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Application\Exception;

use InvalidArgumentException;
use Tilttek\GameEngine\Exception\GameEngineException;

/**
 * A seat identifier that looks like it names a person — an email address, or a reference to a member or a user —,
 * refused when a table opens: the log keeps every seat for good, so a seat is an identifier of the table alone, and
 * the application keeps which person sits there.
 */
final class IdentifyingSeat extends InvalidArgumentException implements GameEngineException
{
    public static function email(string $seat): self
    {
        return new self(\sprintf('The seat "%s" looks like an email address: a seat is an opaque identifier of the table, "seat:1", and the application keeps who sits there.', $seat));
    }

    public static function member(string $seat): self
    {
        return new self(\sprintf('The seat "%s" looks like a reference to a member or a user: a seat is an opaque identifier of the table, "seat:1", and the application keeps who sits there.', $seat));
    }
}
