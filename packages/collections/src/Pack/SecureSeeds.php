<?php

declare(strict_types=1);

namespace Tilttek\Collections\Pack;

use Override;

/**
 * Seeds of 32 bytes from the system's secure generator.
 */
final readonly class SecureSeeds implements SeedSource
{
    #[Override]
    public function seed(): string
    {
        return random_bytes(32);
    }
}
