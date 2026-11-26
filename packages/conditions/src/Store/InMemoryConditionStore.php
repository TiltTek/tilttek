<?php

declare(strict_types=1);

namespace Tilttek\Conditions\Store;

use Override;
use Tilttek\Conditions\CharacterConditions;
use Tilttek\Conditions\Contract\ConditionStore;
use Tilttek\Conditions\Exception\ConditionConflict;

/**
 * The conditions in memory, for tests: they last as long as the object.
 */
final class InMemoryConditionStore implements ConditionStore
{
    /** @var array<string, CharacterConditions> by character */
    private array $conditions = [];

    #[Override]
    public function conditions(string $character): ?CharacterConditions
    {
        return $this->conditions[$character] ?? null;
    }

    #[Override]
    public function save(CharacterConditions $conditions): CharacterConditions
    {
        if ($conditions->version !== ($this->conditions[$conditions->character]->version ?? 0)) {
            throw ConditionConflict::changed($conditions->character);
        }

        return $this->conditions[$conditions->character] = $conditions->withVersion($conditions->version + 1);
    }
}
