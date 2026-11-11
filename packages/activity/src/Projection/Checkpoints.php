<?php

declare(strict_types=1);

namespace Tilttek\Activity\Projection;

/**
 * Where each projection stopped reading the journal, by its name.
 */
interface Checkpoints
{
    /**
     * The position of the last entry the projection has seen; 0 when it has seen none.
     */
    public function position(string $projection): int;

    public function save(string $projection, int $position): void;
}
