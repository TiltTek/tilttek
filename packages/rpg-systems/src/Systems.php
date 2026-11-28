<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems;

use Tilttek\RpgSystems\Exception\InvalidRecord;
use Tilttek\RpgSystems\Exception\UnknownRecord;

/**
 * The systems an application knows, by key: what the other tools look a system up in.
 */
final readonly class Systems
{
    /** @var array<string, GameSystem> */
    private array $systems;

    /**
     * @throws InvalidRecord when two systems share a key
     */
    public function __construct(GameSystem ...$systems)
    {
        $byKey = [];

        foreach ($systems as $system) {
            if (isset($byKey[$system->key])) {
                throw InvalidRecord::because(\sprintf('Two systems have the key "%s".', $system->key));
            }

            $byKey[$system->key] = $system;
        }

        $this->systems = $byKey;
    }

    /**
     * @throws UnknownRecord
     */
    public function get(string $key): GameSystem
    {
        return $this->systems[$key] ?? throw UnknownRecord::of('system', $key);
    }

    public function has(string $key): bool
    {
        return isset($this->systems[$key]);
    }

    /**
     * @return list<GameSystem>
     */
    public function all(): array
    {
        return array_values($this->systems);
    }

    /**
     * These systems with one more, or one replaced by a system of the same key.
     */
    public function with(GameSystem $system): self
    {
        return new self(...array_values([...$this->systems, $system->key => $system]));
    }
}
