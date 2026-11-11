# tilttek/quests-bundle

**Quests and challenges for a Symfony application, from the journal of its members' activity.**

tilttek/quests-bundle integrates `tilttek/quests` with Symfony. The quests — their steps, genres, dates, chains and
rewards — are written in the configuration and checked when the container is compiled, or kept in the database and
edited by the application. A quest that comes back at computed dates — a week from Easter, the days around a solstice —
is read by `phil/almanac`. The progress, the members' acceptances, the journal of accomplished quests and the duels are
kept in Doctrine DBAL, in five tables the bundle adds to the schema the ORM generates, so that the application's
migrations create them. The projection that turns the members' activity into progress is tagged for
`tilttek/activity-bundle`, which keeps it up to date at each gesture, catches it up and rebuilds it. A quest
accomplished becomes an event of the application's dispatcher carrying its rewards and the members they go to, and so
do a duel won and a duel expired; Twig functions and two templates show the list and the detail of the quests; commands
list the quests, show a member's and expire the duels; and a member's quests are exported and erased with their account
through `phil/privacy-bundle`.

It is for a Symfony application that gives its members goals to reach, alone, in teams, as a community or against each
other. The library alone suits an application without Symfony.

It requires `tilttek/quests`, Doctrine DBAL, the PSR event dispatcher and Symfony's configuration and container;
`phil/almanac`, the ORM, the console, Twig, the translator and `phil/privacy-bundle` are optional, and the bundle
registers what uses them only when they are installed.

The design follows the library: the stores implement its four contracts, `DbalCatalog` its writable catalog,
`AlmanacScheduleReader` its schedule readers, `QuestsServices` wires the services, `DispatchingObserver` turns what the
library tells into events, `QuestsExtension` is the Twig side.

## Status

| Part | State |
|---|---|
| Configuration of the quests, the stores, the catalog, the timezone and the duels, checked at compile time | Implemented and tested |
| `DbalContributionStore`, `DbalEnrolmentStore`, `DbalCompletionStore`, `DbalDuelStore` and the tables, with the schema listener | Implemented and tested |
| `DbalCatalog`: quests kept in the database as JSON definitions, chains checked on save and removal | Implemented and tested |
| `AlmanacSchedule` and `AlmanacScheduleReader`: quests that come back at dates `phil/almanac` computes | Implemented and tested |
| The projection tagged for `tilttek/activity-bundle` | Implemented and tested |
| Events `QuestCompleted`, `DuelWon` and `DuelExpired` | Implemented and tested |
| Twig functions and the templates of the list and the detail, in English and French | Implemented and tested |
| Commands `tilttek:quests:list`, `tilttek:quests:show` and `tilttek:quests:expire` | Implemented and tested |
| Export and erasure of a member for `phil/privacy-bundle` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the tables and the projection. |
| [Configuration](doc/configuration.md) | Every option, with an example, and the quests that come back. |
| [Database](doc/database.md) | The five tables, their keys, and the catalog kept in the database. |
| [Events](doc/events.md) | The events the bundle dispatches and how to give a quest's rewards. |
| [Templates](doc/templates.md) | The Twig functions, the list and the detail, and their translations. |
| [Commands](doc/commands.md) | Listing the quests, showing a member's, expiring the duels. |
| [Privacy](doc/privacy.md) | What a member's export holds and what their erasure removes. |
| [Extension points](doc/extension-points.md) | Replacing a store or the catalog, another schedule, the observer. |
| [Exceptions](doc/exceptions.md) | What the bundle raises. |
| [Testing](doc/testing.md) | The bundle's tests, and testing an application that uses it. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```php
// config/bundles.php
Tilttek\ActivityBundle\TilttekActivityBundle::class => ['all' => true],
Tilttek\QuestsBundle\TilttekQuestsBundle::class => ['all' => true],
```

```yaml
# config/packages/tilttek_quests.yaml
tilttek_quests:
    timezone: America/Toronto
    quests:
        first-steps:
            ordered: true
            steps: [{ code: play, verbs: ['game.played'], count: 3 }, { code: write, verbs: ['sheet.published'] }]
            rewards: [{ kind: points, code: xp, amount: 50 }]
        easter-hunt:
            recurs: { from: 'easter - 2 days', until: 'easter + 1 day' }
            steps: [{ verbs: ['egg.found'], count: 10 }]
```

```php
#[AsEventListener]
public function __invoke(QuestCompleted $event): void
{
    foreach ($event->beneficiaries as $member) {
        foreach ($event->rewards() as $reward) {
            // give the reward through the packages the application has
        }
    }
}
```

## Requirements

PHP 8.5 or later, Symfony 8.2 and Doctrine DBAL 4.5.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
