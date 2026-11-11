<?php

declare(strict_types=1);

namespace Tilttek\Progression\Store;

use Phil\Resource\ResourceReference;

/**
 * A subject's experience on a track in a season.
 */
final readonly class Total
{
    public function __construct(
        public string $track,
        public ResourceReference $subject,
        public string $season,
        public int $experience,
    ) {
    }
}
