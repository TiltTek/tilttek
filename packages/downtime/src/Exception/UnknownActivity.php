<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Exception;

use DomainException;

/**
 * The rules of the system declare no activity of the key, or the system declares no rules: nothing is kept.
 */
final class UnknownActivity extends DomainException implements DowntimeException
{
    /**
     * @param string $system   the identifier of the game system
     * @param string $activity the key of the activity asked for
     */
    public function __construct(public readonly string $system, public readonly string $activity)
    {
        parent::__construct(\sprintf('The system %s declares no activity %s.', $system, $activity));
    }

    public static function in(string $system, string $activity): self
    {
        return new self($system, $activity);
    }
}
