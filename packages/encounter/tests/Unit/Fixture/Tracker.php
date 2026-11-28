<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Tests\Unit\Fixture;

use Override;
use Tilttek\Encounter\Contract\InitiativeHandOff;
use Tilttek\Encounter\Initiative\InitiativeRoster;

/**
 * Records the rosters handed to it, as an initiative tracker would start them.
 */
final class Tracker implements InitiativeHandOff
{
    /** @var list<InitiativeRoster> */
    public array $rosters = [];

    #[Override]
    public function handOff(InitiativeRoster $roster): void
    {
        $this->rosters[] = $roster;
    }
}
