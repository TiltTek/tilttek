<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Tests\Unit\Fixture;

use Override;
use Tilttek\GameCatalog\Contract\PlayHistory;

/**
 * The plays of the members, set by the test.
 */
final class CountedPlays implements PlayHistory
{
    /** @var array<string, int> by member and entry */
    public array $played = [];

    /** @var array<string, int> by member and entry */
    public array $ran = [];

    #[Override]
    public function played(string $member, string $entry): int
    {
        return $this->played[$member.' '.$entry] ?? 0;
    }

    #[Override]
    public function ran(string $member, string $entry): int
    {
        return $this->ran[$member.' '.$entry] ?? 0;
    }
}
