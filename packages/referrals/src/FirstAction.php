<?php

declare(strict_types=1);

namespace Tilttek\Referrals;

use DateTimeImmutable;
use DateTimeZone;
use Tilttek\Referrals\Exception\InvalidReferral;

/**
 * The first real action of a newcomer, as the application tells it: a game played, a character sheet published, a
 * session attended — never the sign-up itself. The kind is the application's name for it, like `game.played`; the
 * moment is kept in UTC.
 */
final readonly class FirstAction
{
    public DateTimeImmutable $at;

    public function __construct(
        public string $kind,
        DateTimeImmutable $at,
    ) {
        if (1 !== preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $kind)) {
            throw InvalidReferral::because(\sprintf('An action kind is 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter; "%s" is not.', $kind));
        }

        $this->at = $at->setTimezone(new DateTimeZone('UTC'));
    }
}
