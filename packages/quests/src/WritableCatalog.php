<?php

declare(strict_types=1);

namespace Tilttek\Quests;

use Tilttek\Quests\Exception\InvalidQuest;

/**
 * A catalog the application edits: a quest added or changed under its code from its definition, as `QuestReader`
 * reads it, and a quest removed. Removing a quest leaves its completions in the journal of accomplished quests.
 */
interface WritableCatalog extends Catalog
{
    /**
     * @param array<array-key, mixed> $definition
     *
     * @throws InvalidQuest when the definition is refused, or would break a chain
     */
    public function save(string $code, array $definition): Quest;

    public function remove(string $code): void;
}
