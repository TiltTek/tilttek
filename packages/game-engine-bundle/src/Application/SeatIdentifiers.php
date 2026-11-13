<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Application;

use Tilttek\GameEngineBundle\Application\Exception\IdentifyingSeat;

/**
 * The guard on the seats of a new table: a seat is an opaque identifier of the table — `seat:1`, `north`, `red` —,
 * never one that names a person, since the log keeps it for good and is shown to anyone once the game is over.
 *
 * It refuses what looks like an email address — an `@`, or a dotted name that ends in a common mail domain, such as
 * `ann.example.com` — and what looks like a reference to a person — `member:44`, `user-7`, `account_12`, `uid42`.
 */
final class SeatIdentifiers
{
    /** The words a reference to a person starts with, followed by a separator or a number. */
    private const string MEMBER = '/^(?:member|user|account|customer|subscriber|profile|person|uid|login|e-?mail|mail)s?(?:[:._\/#-].+|\d+)$/iD';

    /** A dotted name ending in a common mail domain, or anything with an `@`, raw or encoded. */
    private const string EMAIL = '/@|%40|^[^.]+(?:\.[^.]+)*\.(?:com|net|org|edu|gov|info|biz|io|me|ca|qc|fr|be|ch|uk|us|de|es|it|nl|eu)$/iD';

    private function __construct()
    {
    }

    /**
     * @param list<string> $seats
     *
     * @throws IdentifyingSeat
     */
    public static function check(array $seats): void
    {
        foreach ($seats as $seat) {
            if (1 === preg_match(self::EMAIL, $seat)) {
                throw IdentifyingSeat::email($seat);
            }

            if (1 === preg_match(self::MEMBER, $seat)) {
                throw IdentifyingSeat::member($seat);
            }
        }
    }
}
