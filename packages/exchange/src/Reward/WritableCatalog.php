<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Reward;

/**
 * A catalogue the application edits: a reward added or changed under its code, a reward removed. Removing a reward
 * leaves its reservations as they are.
 */
interface WritableCatalog extends Catalog
{
    public function save(Reward $reward): void;

    public function remove(string $code): void;
}
