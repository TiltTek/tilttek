<?php

declare(strict_types=1);

namespace Tilttek\Quests\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Quests\Completion;

/**
 * The journal of accomplished quests: one completion per quest, occurrence and subject.
 */
interface CompletionStore
{
    /**
     * Keeps the completion; false when the subject already accomplished this occurrence of the quest.
     */
    public function add(Completion $completion): bool;

    public function find(string $quest, string $occurrence, ResourceReference $subject): ?Completion;

    /**
     * The subject's latest completion of the quest, whatever its occurrence.
     */
    public function latest(string $quest, ResourceReference $subject): ?Completion;

    /**
     * The quests a subject accomplished, by moment.
     *
     * @return list<Completion>
     */
    public function ofSubject(ResourceReference $subject): array;

    /**
     * Forgets the completions from `since` on — all of them when `since` is null —, before a rebuild, and returns
     * them.
     *
     * @return list<Completion>
     */
    public function forget(?DateTimeImmutable $since): array;

    /**
     * @return int how many completions of the subject were removed
     */
    public function eraseSubject(ResourceReference $subject): int;
}
