<?php

declare(strict_types=1);

namespace Tilttek\CheckInRewards;

use DateTimeZone;
use Phil\Resource\ResourceReference;
use Tilttek\Activity\Context;
use Tilttek\CheckInRewards\Exception\InvalidPresence;

/**
 * What an admission at the door means to the application: the member who came in, what they came in to — a session, a
 * table, a stand, or the event itself —, the event it belongs to, the place and the track, and the timezone the days of
 * the event are counted in.
 *
 * The event, the place and the track are written in the context of the activities, which holds strings of at most
 * {@see self::MAX_CODE_LENGTH} bytes: the event as its reference, `event:2027-con`, the place and the track as the
 * application's codes, `table-12`, `hall-b`.
 */
final readonly class Presence
{
    public const int MAX_CODE_LENGTH = Context::MAX_STRING_LENGTH;

    public function __construct(
        public ResourceReference $member,
        public ResourceReference $object,
        public ?ResourceReference $event = null,
        public ?string $place = null,
        public ?string $track = null,
        public DateTimeZone $timezone = new DateTimeZone('UTC'),
    ) {
        if ($event instanceof ResourceReference && !self::fits($event->toString())) {
            throw InvalidPresence::because(\sprintf('The reference of an event is at most %d bytes; "%s" is %d.', self::MAX_CODE_LENGTH, $event->toString(), \strlen($event->toString())));
        }

        foreach (['place' => $place, 'track' => $track] as $what => $code) {
            if (null !== $code && !self::fits($code)) {
                throw InvalidPresence::because(\sprintf('The %s of a presence is 1 to %d bytes; this one is %d.', $what, self::MAX_CODE_LENGTH, \strlen($code)));
            }
        }
    }

    /**
     * Whether a code fits in the context of an activity: 1 to {@see self::MAX_CODE_LENGTH} bytes.
     *
     * @internal
     */
    public static function fits(string $code): bool
    {
        return '' !== $code && \strlen($code) <= self::MAX_CODE_LENGTH;
    }
}
