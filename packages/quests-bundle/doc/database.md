# Database

The bundle adds five tables to the schema of its connection:

| Table | Primary key | Holds |
|---|---|---|
| `tilttek_quests_contribution` | quest, step, activity | What each activity brought to a step: member, value, moment |
| `tilttek_quests_enrolment` | quest, member | A member's acceptance — under a team — and abandonment |
| `tilttek_quests_completion` | quest, occurrence, subject | The journal of accomplished quests |
| `tilttek_quests_duel` | duel_key | The duels: sides, status, acceptance, end, winner |
| `tilttek_quests_definition` | code | The quests' definitions as JSON, for `catalog: doctrine` |

Members, teams and subjects are written as `kind:identifier`, up to 320 bytes; dates in UTC, to the second.

**A contribution and a completion are written once.** Their primary key decides: the insertion writes nothing when the
row is there — `ON CONFLICT DO NOTHING` on PostgreSQL, `INSERT IGNORE` on MySQL and MariaDB, `INSERT OR IGNORE` on
SQLite; on another platform, a plain insertion whose unique violation reaches the caller. An enrolment, a duel and a
definition are replaced in one transaction.

## A catalog in the database

With `catalog: doctrine`, the `WritableCatalog` service is `DbalCatalog`:

```php
$catalog->save('winter-reading', ['admission' => 'accept', 'steps' => [['verbs' => ['book.read'], 'total' => 300]]]);
$catalog->remove('winter-reading');
```

A definition is read before it is saved, and the chains of the whole catalog are checked: a quest following one that
does not exist, or a removal that would leave a quest following nothing, is refused. Every question reads the table
again, so a quest saved is seen at once; removing a quest leaves its completions in the journal. After changing a quest
already under way, rebuild the projection.
