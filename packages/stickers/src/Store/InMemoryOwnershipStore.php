<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Store;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Stickers\Contract\OwnershipStore;

/**
 * The stickers owned, in memory, for tests and for an application that keeps them for one process.
 */
final class InMemoryOwnershipStore implements OwnershipStore
{
    /** @var array<string, array<string, string>> */
    private array $owned = [];

    #[Override]
    public function grant(ResourceReference $holder, string $sticker): bool
    {
        if (isset($this->owned[$holder->toString()][$sticker])) {
            return false;
        }

        $this->owned[$holder->toString()][$sticker] = $sticker;

        return true;
    }

    #[Override]
    public function revoke(ResourceReference $holder, string $sticker): bool
    {
        if (!isset($this->owned[$holder->toString()][$sticker])) {
            return false;
        }

        unset($this->owned[$holder->toString()][$sticker]);

        return true;
    }

    #[Override]
    public function owns(ResourceReference $holder, string $sticker): bool
    {
        return isset($this->owned[$holder->toString()][$sticker]);
    }

    #[Override]
    public function owned(ResourceReference $holder): array
    {
        return array_map(strval(...), array_keys($this->owned[$holder->toString()] ?? []));
    }

    #[Override]
    public function eraseHolder(ResourceReference $holder): int
    {
        $count = \count($this->owned[$holder->toString()] ?? []);
        unset($this->owned[$holder->toString()]);

        return $count;
    }
}
