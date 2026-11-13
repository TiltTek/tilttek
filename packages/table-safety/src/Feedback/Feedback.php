<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Feedback;

use DateTimeImmutable;
use Phil\ContentWarnings\Key;
use Phil\Resource\ResourceReference;
use Tilttek\TableSafety\Exception\InvalidAnswer;

/**
 * The stars and wishes a participant gave after one session of a table. A session is named by a key the application
 * chooses, such as `2026-11-12` or `session-3`.
 */
final readonly class Feedback
{
    public function __construct(
        public ResourceReference $table,
        public string $session,
        public ResourceReference $participant,
        public StarsAndWishes $starsAndWishes,
        public DateTimeImmutable $givenAt,
    ) {
        self::assertSession($session);
    }

    public static function assertSession(string $session): void
    {
        if (!Key::isValid($session)) {
            throw InvalidAnswer::because(\sprintf('"%s" is not a session key.', InvalidAnswer::excerpt($session)));
        }
    }
}
