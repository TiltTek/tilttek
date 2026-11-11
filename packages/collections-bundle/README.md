# tilttek/collections-bundle

**Collectible items for Symfony: the catalog, the members' copies, the item of the day, packs and dice, in Doctrine DBAL.**

tilttek/collections-bundle integrates `tilttek/collections` with Symfony. The catalog — rarities, items, sets, packs
— is configured, checked when the container is compiled, or kept in the database and edited one definition at a time.
The copies, the sets completed, the packs opened, the dice chosen and the calendar of the item of the day live in six
tables of a Doctrine DBAL connection, added to the schema the ORM generates so that the application's migrations
create them, each copy written once even when two processes give it. The item of the day is given on the members'
activity by a projection `tilttek/activity-bundle` keeps up to date, on fixed dates, on dates `phil/almanac` computes
and by a draw weighted by rarity on the free days. A copy given and a set completed — with its rewards — become events
of the application's dispatcher. The bundle provides the inventory `tilttek/exchange` trades through and the
appearance of a member's dice for `tilttek/dice-bundle`, Twig functions and templates for the album, the item of the
day and the odds of a pack, commands that generate the calendar, give a copy and show an album, and the export and
erasure of a member for `phil/privacy-bundle`.

It is for a Symfony application that gives its members something to collect. Its dependencies are
`tilttek/collections`, Doctrine DBAL, `phil/resource`, the PSR event dispatcher and Symfony's configuration and
dependency injection; every other integration is registered only when its package is installed.

The design follows the library: the stores implement its contracts on DBAL, `CollectionsServices` wires the services
from the configuration, and adapters under `Infrastructure` meet the other packages — `Almanac`, `Exchange`, `Dice`,
`Event`, `Console`, `Twig`, `Privacy`. The bundle has no model of its own.

## Status

| Part | State |
|---|---|
| `TilttekCollectionsBundle`, `CollectionsServices`: the configuration, checked when the container is compiled, and the services | Implemented and tested |
| `CollectionsTables`, `CollectionsSchemaListener`, `Rows`: the six tables in the generated schema, the idempotent insertion per platform | Implemented and tested |
| `DbalCopyStore`, `DbalCompletionStore`, `DbalOpeningStore`, `DbalChoiceStore`, `DbalDailyStore`: the library's stores on DBAL | Implemented and tested |
| `DbalCatalog`: the catalog in the database, edited one definition at a time | Implemented and tested |
| `AlmanacDateRule`: items of the day on the dates `phil/almanac` computes | Implemented and tested |
| `CollectionsInventory`: the copies as the inventory of `tilttek/exchange` | Implemented and tested |
| `CollectionDiceAppearance`: the chosen die as the appearance of `tilttek/dice-bundle` | Implemented and tested |
| `DispatchingObserver`, `CopyGranted`: the events | Implemented and tested |
| `DailyCommand`, `GrantCommand`, `AlbumCommand`: the commands | Implemented and tested |
| `CollectionsExtension` and the templates `album`, `daily`, `odds`, in English and French | Implemented and tested |
| `CollectionsPrivacy`: the export and erasure of a member | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering the bundle, the migration. |
| [Configuration](doc/configuration.md) | Every key, with its default. |
| [The database](doc/database.md) | The six tables, the catalog in the database, concurrency. |
| [The item of the day](doc/item-of-the-day.md) | The calendar, its dates, its draw, the projection and the command. |
| [Extension points](doc/extension-points.md) | Trades through `tilttek/exchange`, dice for `tilttek/dice-bundle`, seeds, the catalog. |
| [Events](doc/events.md) | `CopyGranted` and `SetCompleted`. |
| [Templates](doc/templates.md) | The Twig functions and the three templates. |
| [Commands](doc/commands.md) | `tilttek:collections:daily`, `tilttek:collections:grant`, `tilttek:collections:album`. |
| [Privacy](doc/privacy.md) | The module of `phil/privacy-bundle`. |
| [Exceptions](doc/exceptions.md) | What the bundle raises. |
| [Testing](doc/testing.md) | The bundle's tests, and testing an application that uses it. |
| [Reference](doc/reference.md) | Every class of the bundle, with its role. |

## Quick start

```sh
composer require tilttek/collections-bundle tilttek/activity-bundle
```

```yaml
# config/packages/tilttek_collections.yaml
tilttek_collections:
    timezone: America/Toronto
    rarities: { common: 70, rare: 25, legendary: 5 }
    items:
        deckhand: { name: { en: Deckhand, fr: Matelot }, rarity: common, sets: [crew] }
        captain: { name: Captain, rarity: legendary, sets: [crew] }
    sets:
        crew: { name: The crew, rewards: [{ kind: points, code: xp, amount: 50 }] }
    daily:
        enabled: true
        verbs: ['page.viewed']
```

```twig
{{ include('@TilttekCollections/album.html.twig', {album: tilttek_collections_album('member:' ~ app.user.id), locale: app.request.locale}) }}
```

## Requirements

PHP 8.5 or later, Symfony 8.2, Doctrine DBAL 4.5.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
