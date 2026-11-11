# Quests and steps

A `Quest` is a list of `Step`s a subject accomplishes. Each step reads the journal of `tilttek/activity`.

## Steps

```php
new Step('play', new Scope(['game.played'], ['system' => 'dnd5e']), 3);
new Step('read', new Scope(['book.read']), 300, StepMeasure::Total);
new Step('publish', new Scope(['sheet.published'])); // a first time
```

- **The scope** names the verbs by pattern — `game.played`, `game.*` or `*` — and the facts the activity's context
  must hold, compared strictly: `true` is not `1`.
- **The measure** says what counts: `Count`, each accepted activity counts one; `Total`, its quantity counts.
- **The target** is 1 to 10^12; a step asking for one activity is a first time.
- **A step's code** is 1 to 64 lower-case letters, digits, `_`, `.` or `-`, starting with a letter, unique in its
  quest.

## Ordered or free

A quest has 1 to 50 steps. Free steps are done in any order, and the quest is accomplished at the moment its last step
is done. **Ordered steps** count an activity for a step only from the moment the step before it was done; a step whose
previous one is not done counts nothing. One activity may count for two steps at once when it falls in both.

## Rewards

A quest declares up to 20 `Reward`s — a kind, what it names in that kind, an amount:

```php
new Reward('points', 'xp', 50);
new Reward('badge', 'explorer');
new Reward('item', 'die:crystal-d20');
```

They are never executed. When the quest is accomplished, `QuestCompleted` carries them with the members they go to,
and the application gives them — a movement of `tilttek/points`, a manual award of `tilttek/badges`, an item of a
collection — through the packages it has.

## A definition from plain values

`QuestReader` reads a quest as a configuration or a database holds it:

```yaml
winter-reading:
    kind: personal            # community, team or duel
    ordered: true
    admission: accept         # or automatic, for a personal or community quest
    opens: '2026-12-21'       # and closes, read in the reader's timezone
    closes: '2027-03-20'
    after: autumn-reading
    steps:
        - { code: start, verbs: ['book.opened'] }
        - { code: read, verbs: ['book.read'], total: 300, context: { genre: rpg } }
    rewards: [{ kind: points, code: xp, amount: 50 }]
```

A step without `count` nor `total` asks for one activity; a step without `code` is named after its place, `step-1`,
`step-2`… A key no quest has is refused, so a typo does not pass silently. A duel takes `duel: { length: P7D }`.
