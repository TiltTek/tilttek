<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Message;

/**
 * The message of a match whose result was recorded — or corrected, from revision 2 —, with its identifier and its
 * revision only: a play log or a rating reads the result from the store.
 */
final readonly class MatchResultRecorded
{
    public function __construct(
        public string $match,
        public int $revision,
    ) {
    }
}
