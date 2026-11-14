# Upgrading

## From 0.1 to 0.2

### The journal's table

The table `tilttek_dice_roll` has a nullable `context` column, indexed with `rolled_at`. Generate the migration:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

or write it by hand. On MySQL, MariaDB and PostgreSQL:

```sql
ALTER TABLE tilttek_dice_roll ADD context VARCHAR(320) DEFAULT NULL;
CREATE INDEX tilttek_dice_roll_context ON tilttek_dice_roll (context, rolled_at);
```

The rows already kept have no context; nothing else changes in them, and they replay as before.

### The journal's port

`RecordedRollRepository` has two more methods: `getMany(array $ids): array`, the rolls of these identifiers in the order
asked, each once, the unknown ones skipped, for at most `RecordedRollRepository::MANY` identifiers; and
`ofContext(ResourceReference $context, int $limit): array`, the rolls of a context the latest first. An application
with its own implementation of the port adds them. The bundle's implementations, on Doctrine and in memory, have them.

### Rolling

`rollReplayable()` takes the context of a roll, by name, after the rounding:

```php
$dice->rollReplayable('1d20', $member, context: $table);
```

and `DiceRolls` dispatches `RollRecorded` after each replayable roll it saves, when the application has an event
dispatcher. `rollsIn()` and `recorded()` read the journal by context and by identifiers. A call that passed the first
four arguments works unchanged.

### The appearance of a member's dice

`DieAppearance` takes `ink` and `texture` after `image`, by name. An appearance that gave neither works unchanged. The
detail of a roll writes `--tilttek-dice-ink` next to `--tilttek-dice-color` on a coloured set — black or white,
whichever reads better, when the appearance gives no ink — and `data-dice-texture` when there is a texture. A
stylesheet that wrote a fixed colour for the numbers of coloured dice reads `var(--tilttek-dice-ink)` instead; an
overridden `roll_detail.html.twig` adds the two hooks from the bundle's template.

The appearance in the view of a roll, `DiceExtension::view()`, has the keys `ink` and `texture` too.
