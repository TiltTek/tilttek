# tilttek/table-safety-bundle

**The safety tools of role-playing tables in a Symfony application: the screens where participants answer and raise
signals, the game master's screen, and the signals in real time — anonymous by default.**

tilttek/table-safety-bundle integrates `tilttek/table-safety` with Symfony. The tables' settings, the participants'
lines and veils, consent checklists, session zero answers and stars and wishes, and the signals of play are kept in
Doctrine DBAL, in four tables the bundle adds to the schema the ORM generates, so that the application's migrations
create them. The screens — the table during play, a participant's answers, the session zero, the stars and wishes of a
session, the game master's screen — are built of the recipes of a UX Toolkit kit and work without JavaScript: every
signal is a plain form. A Stimulus controller sends the signals in the background, adds each new one as it is raised and
announces it to screen readers. The X-card, the lines and veils and the consent checklist print as blank cards for a
table played in person, as a page or, with `phil/paged-media`, as a PDF. With a Mercure hub, each signal is pushed to
the private topic of its table — or of its game master, for a discreet signal — with identifiers only. The application
says who takes part in a table and who runs it through one interface; a command purges old signals; and a participant's
answers are exported and erased with their account through `phil/privacy-bundle`.

It is for a Symfony application that runs tables — a club, a convention, a play-by-post site, a virtual tabletop — and
wants their safety tools without exposing who said what. The library alone suits an application without Symfony.

It requires `tilttek/table-safety`, `phil/content-warnings-bundle` for the catalogue of themes, Doctrine DBAL, Symfony's
form, HTTP, routing and CSRF components, and Twig; the ORM, the console, the Mercure component, `phil/paged-media` and
`phil/privacy-bundle` are optional, and the bundle registers what uses them only when they are installed or configured.

## Status

| Part | State |
|---|---|
| Configuration of the stores, the access, the layout, the defaults of a table, the hub and the retention | Implemented and tested |
| `DbalTableStore`, `DbalParticipationStore`, `DbalSignalStore`, `DbalFeedbackStore` and the four tables, with the schema listener | Implemented and tested |
| The forms of the lines and veils, the checklist, the session zero, the stars and wishes, the tools of an announcement and the settings | Implemented and tested |
| The screens of the table, the answers, the session zero, the stars and wishes and the game master, in English and French | Implemented and tested |
| The routes of the signals: raising, the feed, marking as seen | Implemented and tested |
| The signals pushed to Mercure, and the subscription of each viewer | Implemented and tested |
| The Stimulus controller of the signals | Implemented |
| The printable cards — X-card, lines and veils, consent checklist — as a page and as a PDF | Implemented and tested |
| The command `tilttek:table-safety:purge-signals` | Implemented and tested |
| Export and erasure of a participant for `phil/privacy-bundle` | Implemented and tested |
| The Symfony Flex recipe, in `recipe/` | Prepared, not published |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the routes, the tables, the access and the recipe. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [Screens](doc/screens.md) | The routes, the templates, the printable cards, the kit's recipes, the forms, the Stimulus controller and accessibility. |
| [Real time](doc/real-time.md) | The signals pushed to Mercure, the topics and the subscriptions. |
| [Database](doc/database.md) | The four tables and their keys. |
| [Upgrading](doc/upgrading.md) | The migrations an application runs when the tables change. |
| [Privacy](doc/privacy.md) | What stays anonymous, a participant's export and erasure, and the purge of signals. |
| [Extension points](doc/extension-points.md) | Replacing the access, a store, the catalogue, the templates. |
| [Testing](doc/testing.md) | The bundle's tests, and testing an application that uses it. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```php
// config/bundles.php
Phil\ContentWarningsBundle\PhilContentWarningsBundle::class => ['all' => true],
Tilttek\TableSafetyBundle\TilttekTableSafetyBundle::class => ['all' => true],
```

```yaml
# config/packages/tilttek_table_safety.yaml
tilttek_table_safety:
    table_access: App\TableSafety\TableAccess
    mercure: { hub: mercure.hub.default }

# config/routes/tilttek_table_safety.yaml
tilttek_table_safety:
    resource: '@TilttekTableSafetyBundle/config/routes.php'
```

A participant of `table:7` then opens `/table-safety/table:7`; its game master, `/table-safety/table:7/game-master`.

## Requirements

PHP 8.5 or later with `ext-intl`, Symfony 8.2, Doctrine DBAL 4.6, Twig 3.31.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
