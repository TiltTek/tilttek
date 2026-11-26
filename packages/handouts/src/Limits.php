<?php

declare(strict_types=1);

namespace Tilttek\Handouts;

use Tilttek\Handouts\Exception\InvalidHandout;

/**
 * The bounds of what handouts hold, so that every read stays small: the handouts of a table — 200 by default, 1 to
 * 5000 —, the attachments of a handout — 10 by default, 1 to 50 —, the players an audience names and the audiences a
 * handout is revealed or staged to — 50 by default, 1 to 500 — and the staged reveals one release or one cue reveals —
 * 100 by default, 1 to 1000.
 */
final readonly class Limits
{
    public const int DEFAULT_HANDOUTS_PER_TABLE = 200;

    public const int MAX_HANDOUTS_PER_TABLE = 5000;

    public const int DEFAULT_ATTACHMENTS_PER_HANDOUT = 10;

    public const int DEFAULT_AUDIENCE_PER_HANDOUT = 50;

    public const int MAX_AUDIENCE_PER_HANDOUT = Audience::MAX_PLAYERS;

    public const int DEFAULT_RELEASE_BATCH = 100;

    public const int MAX_RELEASE_BATCH = 1000;

    public function __construct(
        public int $handoutsPerTable = self::DEFAULT_HANDOUTS_PER_TABLE,
        public int $attachmentsPerHandout = self::DEFAULT_ATTACHMENTS_PER_HANDOUT,
        public int $audiencePerHandout = self::DEFAULT_AUDIENCE_PER_HANDOUT,
        public int $releaseBatch = self::DEFAULT_RELEASE_BATCH,
    ) {
        $this->check('handouts per table', $handoutsPerTable, self::MAX_HANDOUTS_PER_TABLE);
        $this->check('attachments per handout', $attachmentsPerHandout, Handout::MAX_ATTACHMENTS);
        $this->check('audience per handout', $audiencePerHandout, self::MAX_AUDIENCE_PER_HANDOUT);
        $this->check('staged reveals per release', $releaseBatch, self::MAX_RELEASE_BATCH);
    }

    private function check(string $what, int $value, int $max): void
    {
        if ($value < 1 || $value > $max) {
            throw InvalidHandout::because(\sprintf('The limit of %s is 1 to %d; it is %d.', $what, $max, $value));
        }
    }
}
