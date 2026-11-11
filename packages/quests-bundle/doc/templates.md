# Templates

## Twig functions

| Function | Returns |
|---|---|
| `tilttek_quests_board('member:44')` | Where every quest but the duels stands for the member, a list of `QuestStatus` |
| `tilttek_quests_status('member:44', 'winter-reading')` | One quest's `QuestStatus` |
| `tilttek_quests_duel('duel:812')` | A duel and how far each side is, a `DuelStanding` |
| `tilttek_quests_domain()` | The translation domain of the templates |

## The list

```twig
{{ include('@TilttekQuests/list.html.twig', {statuses: tilttek_quests_board('member:44')}) }}
```

Each quest open or accomplished, with its name, genre, state, the steps done and the closing date; the closed quests
never accomplished and the locked ones are left out.

## The detail

```twig
{{ include('@TilttekQuests/detail.html.twig', {status: tilttek_quests_status('member:44', 'winter-reading')}) }}
```

The quest's name and description, its state, its team, each step with a progress bar — in a numbered list when the
steps are ordered —, its rewards and when it was accomplished.

## Translations

The templates ship their texts in English and French under the domain `tilttek_quests`. The application writes the
names of its quests in the same domain: `quests.<code>.name`, `quests.<code>.description`,
`quests.<code>.steps.<step>`, and the rewards as `rewards.<kind>` with `%code%` and `%amount%`. The templates ship no
CSS: their classes follow `tilttek-quests-list__…` and `tilttek-quests-detail__…`.
