<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Application\Contract;

/**
 * Where the states of the tables with a memory are kept between draws, by generator and by key — a campaign, a table, a
 * person, as the application names them. The bundle keeps them in a cache pool or in memory; an application replaces
 * the store with its own, on Doctrine or elsewhere, by naming its service.
 */
interface StateStore
{
    /**
     * The state the last draw left, or null when there is none.
     *
     * @return array<string, string>|null
     */
    public function load(string $generator, string $key): ?array;

    /**
     * @param array<string, string> $state
     */
    public function save(string $generator, string $key, array $state): void;

    public function forget(string $generator, string $key): void;
}
