<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Exception;

use Throwable;

/**
 * Every exception of the library: catch this one to catch them all, except `InsufficientPoints`, which the escrow
 * throws as `tilttek/points` does.
 */
interface FriendChallengesException extends Throwable
{
}
