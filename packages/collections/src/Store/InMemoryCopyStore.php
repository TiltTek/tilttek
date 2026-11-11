<?php

declare(strict_types=1);

namespace Tilttek\Collections\Store;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Collections\Copy;
use Tilttek\Collections\Source;

/**
 * The copies in memory, for tests and for trying the collections out.
 */
final class InMemoryCopyStore implements CopyStore
{
    /**
     * @var array<string, Copy>
     */
    private array $copies = [];

    #[Override]
    public function add(Copy $copy): bool
    {
        if (isset($this->copies[$copy->id])) {
            return false;
        }

        $this->copies[$copy->id] = $copy;

        return true;
    }

    #[Override]
    public function find(string $id): ?Copy
    {
        return $this->copies[$id] ?? null;
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        $copies = array_filter($this->copies, static fn (Copy $copy): bool => $copy->owner->equals($member));
        usort($copies, static fn (Copy $a, Copy $b): int => [$a->acquiredAt->getTimestamp(), $a->id] <=> [$b->acquiredAt->getTimestamp(), $b->id]);

        return $copies;
    }

    #[Override]
    public function reassign(string $id, ResourceReference $from, ResourceReference $to): bool
    {
        $copy = $this->find($id);

        if (!$copy instanceof Copy || !$copy->owner->equals($from)) {
            return false;
        }

        $this->copies[$id] = $copy->movedTo($to);

        return true;
    }

    #[Override]
    public function takeBack(string $id, ResourceReference $member): bool
    {
        $copy = $this->find($id);

        if (!$copy instanceof Copy || $copy->moved || !$copy->owner->equals($member)) {
            return false;
        }

        unset($this->copies[$id]);

        return true;
    }

    #[Override]
    public function forget(Source $source, ?DateTimeImmutable $since): int
    {
        $forgotten = array_filter(
            $this->copies,
            static fn (Copy $copy): bool => $source === $copy->source && !$copy->moved && (!$since instanceof DateTimeImmutable || $copy->acquiredAt->getTimestamp() >= $since->getTimestamp()),
        );
        $this->copies = array_diff_key($this->copies, $forgotten);

        return \count($forgotten);
    }

    #[Override]
    public function eraseOwner(ResourceReference $member): int
    {
        $erased = array_filter($this->copies, static fn (Copy $copy): bool => $copy->owner->equals($member));
        $this->copies = array_diff_key($this->copies, $erased);

        return \count($erased);
    }

    /**
     * Empties the store: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->copies = [];
    }
}
