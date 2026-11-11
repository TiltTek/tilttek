<?php

declare(strict_types=1);

namespace Tilttek\Activity\Exception;

use DomainException;
use Tilttek\Activity\Verb;

/**
 * A verb the application did not declare, when it declares its verbs: most often a typing error that would record a
 * gesture no rule ever reads.
 */
final class UnknownVerb extends DomainException implements ActivityException
{
    public static function named(Verb $verb): self
    {
        return new self(\sprintf('The verb "%s" is not declared.', $verb->value));
    }
}
