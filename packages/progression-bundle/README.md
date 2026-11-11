# tilttek/progression-bundle

**Levels, seasons and streaks for a Symfony application, from the journal of its members' activity.**

tilttek/progression-bundle integrates `tilttek/progression` with Symfony. The tracks — their curves, rules, seasons and
milestones — and the streaks are written in the configuration and checked when the container is compiled. The
experience, the milestones reached and the streaks are kept in Doctrine DBAL, in five tables the bundle adds to the
schema the ORM generates, so that the application's migrations create them. The projection that turns the members'
activity into experience and streaks is tagged for `tilttek/activity-bundle`, which keeps it up to date at each
gesture, catches it up and rebuilds it. A level up or down, a milestone reached and a broken streak become events of
the application's dispatcher; Twig functions and two templates show the progress bar of a track and a streak; a command
sweeps the broken streaks, another shows a subject's progression; and a member's progression is exported and erased
with their account through `phil/privacy-bundle`.

It is for a Symfony application that rewards what its members do and shows them how far they have come. The library
alone suits an application without Symfony.

It requires `tilttek/progression`, Doctrine DBAL, the PSR event dispatcher and Symfony's configuration and container;
the ORM, the console, Twig, the translator and `phil/privacy-bundle` are optional, and the bundle registers what uses
them only when they are installed.

The design follows the library: the stores implement its three contracts, `ProgressionServices` wires the services,
`DispatchingObserver` turns what the library tells into events, `ProgressionExtension` is the Twig side.

## Status

| Part | State |
|---|---|
| Configuration of the tracks, streaks, timezones and stores, checked at compile time | Implemented and tested |
| `DbalExperienceStore`, `DbalMilestoneStore`, `DbalStreakStore` and the five tables, with the schema listener | Implemented and tested |
| The projection tagged for `tilttek/activity-bundle`, the member's timezone from the activity's context | Implemented and tested |
| Events `LevelUp`, `LevelDown`, `ReachedMilestone` and `StreakBroken` | Implemented and tested |
| Twig functions and the templates of the progress bar and the streak, in English and French | Implemented and tested |
| Commands `tilttek:progression:sweep` and `tilttek:progression:show` | Implemented and tested |
| Export and erasure of a member for `phil/privacy-bundle` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the tables and the projection. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [Database](doc/database.md) | The five tables, their keys and the insertion written once. |
| [Events](doc/events.md) | The events the bundle dispatches and how to reward a milestone. |
| [Templates](doc/templates.md) | The Twig functions, the progress bar and the streak, and their translations. |
| [Commands](doc/commands.md) | Sweeping the broken streaks and showing a subject. |
| [Privacy](doc/privacy.md) | What a member's export holds and what their erasure removes. |
| [Extension points](doc/extension-points.md) | Replacing a store, the timezone of a member, the observer. |
| [Exceptions](doc/exceptions.md) | What the bundle raises. |
| [Testing](doc/testing.md) | The bundle's tests, and testing an application that uses it. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```php
// config/bundles.php
Tilttek\ActivityBundle\TilttekActivityBundle::class => ['all' => true],
Tilttek\ProgressionBundle\TilttekProgressionBundle::class => ['all' => true],
```

```yaml
# config/packages/tilttek_progression.yaml
tilttek_progression:
    timezone: America/Toronto
    tracks:
        player:
            curve: { type: quadratic, base: 50, levels: 60 }
            rules: { games: { verbs: ['game.played'], experience: 20, cap: { amount: 100, period: day } } }
            milestones: { veteran: { level: 10 } }
    streaks:
        daily-play: { verbs: ['game.played'], grace: { allowed: 1, window: 7 } }
```

```twig
{{ include('@TilttekProgression/bar.html.twig', {standing: tilttek_progression_standing('member:44', 'player')}) }}
```

## Requirements

PHP 8.5 or later, Symfony 8.2, Doctrine DBAL 4.5.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
