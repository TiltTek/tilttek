<?php

declare(strict_types=1);

namespace Tilttek\Predictions;

/**
 * What settling a question did: how many predictions it read, how many earned points and how many points in all, and
 * how many movements of points this call wrote — 0 when every one was written already. A cancelled question settles
 * to nothing.
 */
final readonly class Settlement
{
    public function __construct(
        public Question $question,
        public int $predictions = 0,
        public int $rewarded = 0,
        public int $points = 0,
        public int $written = 0,
    ) {
    }
}
