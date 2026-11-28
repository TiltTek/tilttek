<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Tests\Unit\Fixture;

use Override;
use Tilttek\Downtime\Contract\Roster;

/**
 * The roster of the tests: one game master per campaign, one player per character.
 */
final class Table implements Roster
{
    /**
     * @param array<string, string> $gameMasters by campaign
     * @param array<string, string> $players     by character
     */
    public function __construct(private array $gameMasters, private array $players)
    {
    }

    #[Override]
    public function isGameMaster(string $campaign, string $member): bool
    {
        return ($this->gameMasters[$campaign] ?? null) === $member;
    }

    #[Override]
    public function plays(string $campaign, string $character, string $member): bool
    {
        return isset($this->gameMasters[$campaign]) && ($this->players[$character] ?? null) === $member;
    }
}
