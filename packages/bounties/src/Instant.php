<?php

declare(strict_types=1);

namespace Tilttek\Bounties;

use DateTimeImmutable;
use DateTimeZone;
use Tilttek\Bounties\Exception\InvalidBounty;

/**
 * The one way the library writes a time, in UTC to the microsecond, `2026-11-13T20:15:03.250000Z`.
 *
 * @internal
 */
final class Instant
{
    private const string FORMAT = 'Y-m-d\TH:i:s.u\Z';

    private function __construct()
    {
    }

    public static function utc(DateTimeImmutable $at): DateTimeImmutable
    {
        return $at->setTimezone(new DateTimeZone('UTC'));
    }

    public static function write(DateTimeImmutable $at): string
    {
        return self::utc($at)->format(self::FORMAT);
    }

    /**
     * @throws InvalidBounty when the text is not a time as `write()` writes it
     */
    public static function read(string $text): DateTimeImmutable
    {
        $at = DateTimeImmutable::createFromFormat(self::FORMAT, $text, new DateTimeZone('UTC'));

        if (!$at instanceof DateTimeImmutable || $at->format(self::FORMAT) !== $text) {
            throw InvalidBounty::because(\sprintf('"%s" is not a time written in UTC to the microsecond, as 2026-11-13T20:15:03.250000Z.', $text));
        }

        return $at;
    }
}
