# Exceptions

Every exception of the library implements `Tilttek\Quests\Exception\QuestsException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidQuest` | A code, a step, a reward, a quest, a schedule, a definition, a chain, a duel key or a duration is refused | `"Read" is not a step code: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.` |
| `UnknownQuest` | A quest is asked for by a code none has | `No quest is named "winter".` |
| `UnknownDuel` | A duel is asked for by a key none has | `No duel has the key "duel:9".` |
| `QuestRefused` | What a member asked cannot be: a quest closed, locked, accomplished or never accepted, a team missing or another team, a duel answered by the wrong side or no longer waiting | `"winter-reading" opens once "autumn-reading" is accomplished.` |

The verb patterns and the context conditions of a step are checked by `tilttek/activity`; their messages are carried by
an `InvalidQuest`.
