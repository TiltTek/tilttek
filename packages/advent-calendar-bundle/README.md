# tilttek/advent-calendar-bundle

**Advent calendars in a Symfony application: a box a day, a screen that works without JavaScript, a celebration for
each first opening, and a missed day that is not lost.**

tilttek/advent-calendar-bundle integrates `tilttek/advent-calendar` with Symfony. A site declares its calendars in the
configuration, or edits them in the database through the ORM with the bundle's form; each calendar chooses how long a
box stays openable once its day has come — until the calendar closes by default, so that a member who missed a day
opens its box later, on its day only, or for a few days of catch-up. The openings are kept in Doctrine DBAL, in a table
the bundle adds to the schema the ORM generates, whose primary key makes an opening happen once even when two requests
race. The screen of a calendar is built of the recipes of a UX Toolkit kit and works without JavaScript: each openable
box is a plain form. A Stimulus controller opens a box in the background, puts the opened box in its place, moves the
focus to it and announces it to screen readers. The first opening of a box is told by the library's `BoxOpened` event,
which the application grants the surprise from, and celebrated by the kit's `Celebration` — confetti or fireworks where
motion is not reduced, a message in a live region everywhere. Commands list the calendars, show the boxes of one and
check those of the database; a member's openings are exported and erased with their account through
`phil/privacy-bundle`.

It is for a community or a shop that wants a seasonal ritual without pressure: nothing to buy, no box that vanishes by
default, no ranking of who opened first. The rewards stay in the application, which already knows how to credit points
or award a badge.

It requires `tilttek/advent-calendar`, Doctrine DBAL, Symfony's form, HTTP, routing and CSRF components, and Twig; the
ORM, the console, Twig components with a UX Toolkit kit, and `phil/privacy-bundle` are optional, and the bundle
registers what uses them only when they are installed. Members are references the application gives, `member:44`: the
bundle never reads a name or an e-mail address, and refuses a reference that looks like one.

## Status

| Part | State |
|---|---|
| Configuration of the store, the connection, the entity manager, the member, the layout, the kit, the celebration, the calendars and the privacy kind | Implemented and tested |
| Calendars of the configuration, and of the database through the ORM with `CalendarType` | Implemented and tested |
| `DbalOpeningStore` and its table, added to the schema by `OpeningsSchemaListener`; `InMemoryOpenings` for tests | Implemented and tested |
| The screen of a calendar and the opening of a box, with and without JavaScript, in English and French | Implemented and tested |
| The celebration of a first opening through `BoxOpened` and the kit's `Celebration` | Implemented and tested |
| The Stimulus controller `tilttek--advent-calendar--boxes` | Implemented and tested |
| The commands `tilttek:advent-calendar:list`, `:show` and `:check` | Implemented and tested |
| Export and erasure of a member for `phil/privacy-bundle` | Implemented and tested |
| The Symfony Flex recipe, in `recipe/` | Prepared, not published |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the routes, the table, the member and the recipe. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [Calendars](doc/calendars.md) | Calendars of the configuration and of the database, opening policies, the form and granting the surprises. |
| [Screens](doc/screens.md) | The routes, the templates, the kit's recipes, the Stimulus controller, the celebration and accessibility. |
| [Database](doc/database.md) | The two tables and their keys. |
| [Console](doc/console.md) | The commands that list, show and check the calendars. |
| [Privacy](doc/privacy.md) | What is kept of a member, their export and erasure. |
| [Extension points](doc/extension-points.md) | Replacing the member, a catalogue, the store, the templates. |
| [Testing](doc/testing.md) | The bundle's tests, and testing an application that uses it. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```php
// config/bundles.php
Tilttek\AdventCalendarBundle\TilttekAdventCalendarBundle::class => ['all' => true],
```

```yaml
# config/packages/tilttek_advent_calendar.yaml
tilttek_advent_calendar:
    member_resolver: App\Advent\CurrentMember
    celebration: { enabled: true }
    calendars:
        advent-2026:
            title: Advent 2026
            timezone: America/Toronto
            first_date: '2026-12-01'
            surprises: [{ kind: points, quantity: 10 }, { kind: badge, reference: advent-week }]

# config/routes/tilttek_advent_calendar.yaml
tilttek_advent_calendar:
    resource: '@TilttekAdventCalendarBundle/config/routes.php'
```

A member then opens `/advent-calendar/advent-2026`, and the application grants each surprise from `BoxOpened`.

## Requirements

PHP 8.5 or later with `ext-intl`, Symfony 8.2, Doctrine DBAL 4.6, Twig 3.31.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
