<?php

declare(strict_types=1);

namespace Tilttek\Quests\Store;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Quests\Enrolment;

/**
 * The enrolments in memory, forgotten with the process: for tests, scripts and examples.
 */
final class InMemoryEnrolmentStore implements EnrolmentStore
{
    /**
     * @var array<string, Enrolment>
     */
    private array $enrolments = [];

    #[Override]
    public function save(Enrolment $enrolment): void
    {
        $this->enrolments[$this->key($enrolment->quest, $enrolment->member)] = $enrolment;
    }

    #[Override]
    public function find(string $quest, ResourceReference $member): ?Enrolment
    {
        return $this->enrolments[$this->key($quest, $member)] ?? null;
    }

    #[Override]
    public function ofQuest(string $quest): array
    {
        $enrolments = array_filter($this->enrolments, static fn (Enrolment $enrolment): bool => $enrolment->quest === $quest);
        usort($enrolments, static fn (Enrolment $a, Enrolment $b): int => $a->member->toString() <=> $b->member->toString());

        return $enrolments;
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        $enrolments = array_filter($this->enrolments, static fn (Enrolment $enrolment): bool => $enrolment->member->equals($member));
        usort($enrolments, static fn (Enrolment $a, Enrolment $b): int => $a->quest <=> $b->quest);

        return $enrolments;
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $count = \count($this->enrolments);
        $this->enrolments = array_filter($this->enrolments, static fn (Enrolment $enrolment): bool => !$enrolment->member->equals($member));

        return $count - \count($this->enrolments);
    }

    /**
     * Empties the store: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->enrolments = [];
    }

    private function key(string $quest, ResourceReference $member): string
    {
        return json_encode([$quest, $member->toString()], \JSON_THROW_ON_ERROR);
    }
}
