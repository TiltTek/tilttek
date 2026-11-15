<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * A member's report of a challenge, or of one of its proofs, to the moderators of the site: the reason from a list and
 * an optional note of 500 characters at most.
 */
final readonly class Report
{
    public const int NOTE_LENGTH = 500;

    public string $note;

    public DateTimeImmutable $at;

    public function __construct(
        public string $challenge,
        public ResourceReference $reporter,
        public ReportReason $reason,
        DateTimeImmutable $at,
        public ?string $proof = null,
        string $note = '',
    ) {
        Check::identifier($challenge, 'A challenge identifier');

        if (null !== $proof) {
            Check::identifier($proof, 'A proof identifier');
        }

        $this->note = Check::text($note, 'A note to the moderators', self::NOTE_LENGTH, false, true);
        $this->at = Instant::utc($at);
    }
}
