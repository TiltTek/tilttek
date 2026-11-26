<?php

declare(strict_types=1);

namespace Tilttek\Kudos;

/**
 * What giving did: the thanks, and whether this call gave them or found them already given under the same key.
 */
final readonly class Given
{
    public function __construct(
        public Thanks $thanks,
        public bool $new,
    ) {
    }
}
