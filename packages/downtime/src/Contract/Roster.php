<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Contract;

/**
 * Who runs a campaign and who plays its characters, as the application knows them.
 */
interface Roster
{
    /**
     * Whether the member runs the campaign.
     */
    public function isGameMaster(string $campaign, string $member): bool;

    /**
     * Whether the member plays the character in the campaign.
     */
    public function plays(string $campaign, string $character, string $member): bool;
}
