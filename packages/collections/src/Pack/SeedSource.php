<?php

declare(strict_types=1);

namespace Tilttek\Collections\Pack;

/**
 * Where the seed of a draw comes from: a secure generator in production, a fixed seed in tests. A seed is 16 to 64
 * bytes.
 */
interface SeedSource
{
    public function seed(): string;
}
