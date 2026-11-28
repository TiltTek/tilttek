<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Tests\Unit\Odds;

use Random\Engine;

/**
 * An engine that makes `Randomizer::getInt(1, 6)` give the faces it is told, in order, then 1; it counts its draws.
 */
final class FacesEngine implements Engine
{
    public int $draws = 0;

    /**
     * @param list<int> $faces
     */
    public function __construct(private array $faces = [])
    {
    }

    public function generate(): string
    {
        ++$this->draws;

        return pack('P', (array_shift($this->faces) ?? 1) - 1);
    }
}
