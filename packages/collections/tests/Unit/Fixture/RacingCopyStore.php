<?php

declare(strict_types=1);

namespace Tilttek\Collections\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Collections\Copy;
use Tilttek\Collections\Source;
use Tilttek\Collections\Store\CopyStore;
use Tilttek\Collections\Store\InMemoryCopyStore;

/**
 * Copies in memory that another process moves between the check and the move: every reassignment fails.
 */
final readonly class RacingCopyStore implements CopyStore
{
    public function __construct(
        private InMemoryCopyStore $inner = new InMemoryCopyStore(),
    ) {
    }

    #[Override]
    public function add(Copy $copy): bool
    {
        return $this->inner->add($copy);
    }

    #[Override]
    public function find(string $id): ?Copy
    {
        return $this->inner->find($id);
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        return $this->inner->ofMember($member);
    }

    #[Override]
    public function reassign(string $id, ResourceReference $from, ResourceReference $to): bool
    {
        return false;
    }

    #[Override]
    public function takeBack(string $id, ResourceReference $member): bool
    {
        return $this->inner->takeBack($id, $member);
    }

    #[Override]
    public function forget(Source $source, ?DateTimeImmutable $since): int
    {
        return $this->inner->forget($source, $since);
    }

    #[Override]
    public function eraseOwner(ResourceReference $member): int
    {
        return $this->inner->eraseOwner($member);
    }
}
