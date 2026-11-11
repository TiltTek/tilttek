<?php

declare(strict_types=1);

namespace Tilttek\Collections;

/**
 * A member's collection, as an album shows it: every item of the catalog with the copies the member owns — none for
 * an item still to find —, and each set with its progress.
 */
final readonly class Album
{
    /**
     * @param list<Holding>     $holdings in the catalog's order, every item included
     * @param list<SetProgress> $sets     in the catalog's order
     */
    public function __construct(
        public array $holdings,
        public array $sets,
    ) {
    }

    /**
     * The items the member owns at least once.
     *
     * @return list<Holding>
     */
    public function owned(): array
    {
        return array_values(array_filter($this->holdings, static fn (Holding $holding): bool => $holding->count > 0));
    }

    /**
     * The items the member owns more than once.
     *
     * @return list<Holding>
     */
    public function doubles(): array
    {
        return array_values(array_filter($this->holdings, static fn (Holding $holding): bool => $holding->doubles() > 0));
    }

    public function set(string $code): ?SetProgress
    {
        return array_find($this->sets, static fn (SetProgress $progress): bool => $progress->set->code === $code);
    }
}
