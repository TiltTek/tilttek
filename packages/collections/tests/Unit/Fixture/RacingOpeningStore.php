<?php

declare(strict_types=1);

namespace Tilttek\Collections\Tests\Unit\Fixture;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Collections\Pack\InMemoryOpeningStore;
use Tilttek\Collections\Pack\OpeningStore;
use Tilttek\Collections\Pack\PackOpening;

/**
 * Openings in memory where another request opens the same key between the read and the write: the first `find()`
 * answers nothing, and `add()` finds the other request's opening already kept — or, with none, a key taken whose
 * opening is gone when it is read.
 */
final class RacingOpeningStore implements OpeningStore
{
    public readonly InMemoryOpeningStore $inner;

    private bool $raced = false;

    public function __construct(
        private readonly ?PackOpening $other,
    ) {
        $this->inner = new InMemoryOpeningStore();
    }

    #[Override]
    public function add(PackOpening $opening): bool
    {
        $this->raced = true;

        if (!$this->other instanceof PackOpening) {
            return false;
        }

        $this->inner->add($this->other);

        return $this->inner->add($opening);
    }

    #[Override]
    public function complete(string $key, array $copies): void
    {
        $this->inner->complete($key, $copies);
    }

    #[Override]
    public function find(string $key): ?PackOpening
    {
        return $this->raced ? $this->inner->find($key) : null;
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        return $this->inner->ofMember($member);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return $this->inner->eraseMember($member);
    }
}
