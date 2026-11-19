# Configuration

```yaml
# config/packages/tilttek_advent_calendar.yaml
tilttek_advent_calendar:
    store: doctrine               # or "memory", for tests
    connection: default           # the DBAL connection of the openings
    entity_manager: default       # the entity manager of the calendars the site edits
    member_resolver: App\Advent\CurrentMember
    layout: base.html.twig        # the template the screen extends; it fills its block "body"
    kit_subdirectory: ~           # where the kit's recipes are; the Toolkit's subdirectory when null
    celebration:
        enabled: true             # whether the screen plays the kit's Celebration for a first opening
        effect: confetti          # or "fireworks"
    calendars:
        advent-2026:
            title: Advent 2026
            timezone: America/Toronto
            first_date: '2026-12-01'
            closing_date: '2027-01-06'  # the date of the last box when left out
            policy: until_closing       # "same_day" or "catch_up"
            catch_up_days: 0            # 1 to 31 for "catch_up"
            published: true
            surprises:
                - { kind: points, quantity: 10 }            # the ledger "points" when the reference is left out
                - { kind: badge, reference: advent-week }
                - { kind: content, reference: 'recipe:gingerbread' }
                - { kind: code, reference: gift-codes }
    privacy:
        member_kind: member       # the kind of the reference a data subject of phil/privacy-bundle stands for
```

| Option | Default | Meaning |
|---|---|---|
| `store` | `doctrine` | Where the openings are kept: Doctrine DBAL, or memory for tests. |
| `connection` | `default` | The DBAL connection of the openings, and of the schema listener. |
| `entity_manager` | `default` | The entity manager of the calendars the site edits, read when the ORM is installed. |
| `member_resolver` | none | The service implementing `MemberResolver`; every viewer is a visitor without it. |
| `layout` | `base.html.twig` | The template the screen extends. |
| `kit_subdirectory` | none | The subdirectory of the kit's recipes under the components directory; the Toolkit's when null. |
| `celebration.enabled` | `false` | Whether the screen plays the kit's `Celebration` for a box opened for the first time. |
| `celebration.effect` | `confetti` | What the celebration draws where motion is not reduced: `confetti` or `fireworks`. |
| `calendars` | none | Calendars by identifier; each is checked by the library when the container is built. |
| `privacy.member_kind` | `member` | The kind of a member's reference, for `phil/privacy-bundle`. |

A calendar's identifier is 1 to 64 lower-case letters, digits, `_`, `.` or `-`, starting with a letter or a digit; it
is the key of its openings and of its address, so it does not change once members open boxes. A calendar of the
configuration hides a calendar of the database with the same identifier. A calendar that does not hold together — an
unknown timezone, no surprise or more than 92, a closing before the last box — stops the container from being built,
with the library's reason.
