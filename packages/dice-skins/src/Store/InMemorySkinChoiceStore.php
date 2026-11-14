<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins\Store;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\DiceSkins\Contract\SkinChoiceStore;
use Tilttek\DiceSkins\SkinChoice;

/**
 * The members' choices in memory, for tests and for an application that lives in one process.
 */
final class InMemorySkinChoiceStore implements SkinChoiceStore
{
    private const string ALL = 'all';

    /** @var array<string, array<string, SkinChoice>> */
    private array $choices = [];

    #[Override]
    public function save(SkinChoice $choice): void
    {
        $this->choices[$choice->member->toString()][$this->slot($choice->sides)] = $choice;
    }

    #[Override]
    public function find(ResourceReference $member, ?int $sides): ?SkinChoice
    {
        return $this->choices[$member->toString()][$this->slot($sides)] ?? null;
    }

    #[Override]
    public function remove(ResourceReference $member, ?int $sides): bool
    {
        if (!$this->find($member, $sides) instanceof SkinChoice) {
            return false;
        }

        unset($this->choices[$member->toString()][$this->slot($sides)]);

        return true;
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        $choices = $this->choices[$member->toString()] ?? [];
        usort($choices, static fn (SkinChoice $a, SkinChoice $b): int => ($a->sides ?? 0) <=> ($b->sides ?? 0));

        return $choices;
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $count = \count($this->choices[$member->toString()] ?? []);
        unset($this->choices[$member->toString()]);

        return $count;
    }

    private function slot(?int $sides): string
    {
        return null === $sides ? self::ALL : 'd'.$sides;
    }
}
