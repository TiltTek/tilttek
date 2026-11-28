<?php

declare(strict_types=1);

namespace Tilttek\Factions\Exception;

use RuntimeException;

/**
 * No roster is kept for the campaign.
 */
final class RosterNotFound extends RuntimeException implements FactionsException
{
    public static function forCampaign(string $campaign): self
    {
        return new self(\sprintf('No roster of factions is kept for the campaign %s.', $campaign));
    }
}
