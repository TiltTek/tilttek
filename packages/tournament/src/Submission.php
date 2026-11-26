<?php

declare(strict_types=1);

namespace Tilttek\Tournament;

/**
 * A result as one person entered it: who entered it — a participant or an organiser, by an opaque identifier — and the
 * result.
 */
final readonly class Submission
{
    public function __construct(
        public string $by,
        public Result $result,
    ) {
    }
}
