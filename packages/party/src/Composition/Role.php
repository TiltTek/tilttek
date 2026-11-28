<?php

declare(strict_types=1);

namespace Tilttek\Party\Composition;

use Tilttek\Party\Exception\InvalidParty;
use Tilttek\Party\Text;

/**
 * A role a game system declares for the members of a party — healing, defence, damage, scouting —, under its key in the
 * system, with its name, and the fewest members a balanced party has in it: 1 by default, 0 for a role that is welcome
 * but never missing, at most 10.
 */
final readonly class Role
{
    public const int MAX_MINIMUM = 10;

    public function __construct(
        public string $key,
        public string $name,
        public int $minimum = 1,
    ) {
        Text::key($key, 'a role');
        Text::name($name, 'A role name');

        if ($minimum < 0 || $minimum > self::MAX_MINIMUM) {
            throw InvalidParty::because(\sprintf('The minimum of a role is 0 to %d; %d is not.', self::MAX_MINIMUM, $minimum));
        }
    }
}
