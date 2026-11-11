<?php

declare(strict_types=1);

namespace Tilttek\Quests;

use Tilttek\Quests\Exception\InvalidQuest;

/**
 * Checks the chains of a set of quests: each quest a quest follows is in the set and of the same kind, and no chain
 * loops back on itself.
 */
final class Chains
{
    private function __construct()
    {
    }

    /**
     * @param array<string, Quest> $quests by code
     *
     * @throws InvalidQuest
     */
    public static function check(array $quests): void
    {
        foreach ($quests as $quest) {
            $seen = [$quest->code];
            $current = $quest;

            while (null !== $current->after) {
                $previous = $quests[$current->after] ?? throw InvalidQuest::because(\sprintf('"%s" follows "%s", which no quest is.', $current->code, $current->after));

                if ($previous->kind !== $current->kind) {
                    throw InvalidQuest::because(\sprintf('"%s" follows "%s", a quest of another kind.', $current->code, $previous->code));
                }

                if (\in_array($previous->code, $seen, true)) {
                    throw InvalidQuest::because(\sprintf('The chain of "%s" loops back to "%s".', $quest->code, $previous->code));
                }

                $seen[] = $previous->code;
                $current = $previous;
            }
        }
    }
}
