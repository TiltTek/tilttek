<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Contract;

/**
 * A port to the plays the application records: how many games of an entry a member played, and how many they ran.
 */
interface PlayHistory
{
    public function played(string $member, string $entry): int;

    public function ran(string $member, string $entry): int;
}
