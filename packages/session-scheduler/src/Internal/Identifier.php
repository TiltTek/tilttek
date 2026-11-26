<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Internal;

use Tilttek\SessionScheduler\Exception\InvalidSchedule;

/**
 * The shape of every identifier the library takes. An identifier is stable and opaque, never a name or an address: it
 * holds no "@". It starts with a letter, so that PHP never reads it as an integer key.
 *
 * @internal
 */
final class Identifier
{
    public const int MAX_LENGTH = 128;

    private const string PLAIN = '/^[A-Za-z][A-Za-z0-9_.:-]{0,127}$/D';

    private function __construct()
    {
    }

    /**
     * @param string $what "a member", "a campaign", "a series" or "a session"
     *
     * @throws InvalidSchedule when the value is not an identifier
     */
    public static function check(string $value, string $what): string
    {
        if (1 !== preg_match(self::PLAIN, $value)) {
            throw InvalidSchedule::because(\sprintf('The identifier of %s is 1 to %d letters, digits, "_", ".", ":" or "-", starting with a letter.', $what, self::MAX_LENGTH));
        }

        return $value;
    }

    /**
     * Distinct member identifiers, in their order, at most so many.
     *
     * @param list<string> $members
     *
     * @return list<string>
     *
     * @throws InvalidSchedule when one is not an identifier, one comes twice, or there are too many
     */
    public static function members(array $members, int $max, string $what): array
    {
        if (\count($members) > $max) {
            throw InvalidSchedule::because(\sprintf('%s holds at most %d members.', $what, $max));
        }

        $seen = [];

        foreach ($members as $member) {
            if (isset($seen[self::check($member, 'a member')])) {
                throw InvalidSchedule::because(\sprintf('The member %s comes twice in %s.', $member, lcfirst($what)));
            }

            $seen[$member] = true;
        }

        return $members;
    }
}
