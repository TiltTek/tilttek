<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder\Store;

use Override;
use Tilttek\DeckBuilder\Contract\DeckStore;
use Tilttek\DeckBuilder\DeckVersion;

/**
 * The versions of the decks kept in the process, for tests and scripts.
 */
final class InMemoryDeckStore implements DeckStore
{
    /** @var array<string, array<int, DeckVersion>> */
    private array $versions = [];

    #[Override]
    public function add(DeckVersion $version): bool
    {
        if (isset($this->versions[$version->deck->id][$version->version])) {
            return false;
        }

        $this->versions[$version->deck->id][$version->version] = $version;
        ksort($this->versions[$version->deck->id]);

        return true;
    }

    #[Override]
    public function version(string $deck, int $version): ?DeckVersion
    {
        return $this->versions[$deck][$version] ?? null;
    }

    #[Override]
    public function latest(string $deck): ?DeckVersion
    {
        $versions = $this->versions[$deck] ?? [];

        return [] === $versions ? null : array_last($versions);
    }

    #[Override]
    public function versions(string $deck): array
    {
        return array_values($this->versions[$deck] ?? []);
    }
}
