<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Trade;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\Exception\ExchangeRefused;
use Tilttek\Exchange\Refusal;

/**
 * The members' items in memory, for tests and for trying trades out before the application's collections exist.
 */
final class InMemoryInventory implements Inventory
{
    /**
     * @var array<string, ResourceReference>
     */
    private array $owners = [];

    /**
     * @var array<string, bool>
     */
    private array $tradable = [];

    /**
     * Gives the item to the member; a bound item never changes hands.
     */
    public function give(ResourceReference $item, ResourceReference $member, bool $tradable = true): void
    {
        $this->owners[$item->toString()] = $member;
        $this->tradable[$item->toString()] = $tradable;
    }

    #[Override]
    public function ownerOf(ResourceReference $item): ?ResourceReference
    {
        return $this->owners[$item->toString()] ?? null;
    }

    #[Override]
    public function isTradable(ResourceReference $item): bool
    {
        return $this->tradable[$item->toString()] ?? false;
    }

    #[Override]
    public function move(array $moves): void
    {
        foreach ($moves as $move) {
            $owner = $this->ownerOf($move->item);

            if (!$owner instanceof ResourceReference || !$owner->equals($move->from)) {
                throw ExchangeRefused::because(Refusal::NotOwned, \sprintf('"%s" does not belong to "%s".', $move->item->toString(), $move->from->toString()));
            }
        }

        foreach ($moves as $move) {
            $this->owners[$move->item->toString()] = $move->to;
        }
    }

    /**
     * Empties the inventory: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->owners = [];
        $this->tradable = [];
    }
}
