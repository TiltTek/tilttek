# tilttek/sets-bundle

**Use tilttek/sets in Symfony: stores chosen by configuration, and completions whose event was lost sent again by a
command or a schedule.**

tilttek/sets-bundle registers the `Sets` service of `tilttek/sets` from its configuration: the stores of the
definitions and of the completions the application names — memory, for tests, when it names none —, its clock, its
event dispatcher and the backoff of a holder whose delivery failed. It adds the `tilttek:sets:deliver` command, the
`DeliverPendingCompletions` message and its handler, and the `tilttek_sets` schedule, each when its Symfony component
is installed, so that a completion whose handler threw is told again without waiting for its holder's holdings to
change.

## Status

| Part | State |
|---|---|
| Configuration: stores, clock, backoff and schedule | Implemented and tested |
| `Sets` on the application's event dispatcher | Implemented and tested |
| The `tilttek:sets:deliver` command, with `--limit` and `--holder` | Implemented and tested |
| The `DeliverPendingCompletions` message and its handler | Implemented and tested |
| The `tilttek_sets` schedule, with an interval, a limit and a lock | Implemented and tested |
| Doctrine stores of the definitions and the completions | Not started: the application names its own |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, optional packages and registering the bundle. |
| [Configuration](doc/configuration.md) | Every key, its default and what it sets. |
| [Resuming deliveries](doc/resuming-deliveries.md) | The command, the schedule, its worker and its lock, and routing the message. |
| [Extension points](doc/extension-points.md) | The stores, the clock and the listeners of the events. |
| [Testing](doc/testing.md) | The bundle's tests. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```php
// config/bundles.php
Tilttek\SetsBundle\TilttekSetsBundle::class => ['all' => true],
```

```yaml
# config/packages/tilttek_sets.yaml
tilttek_sets:
    definition_store: App\Sets\DefinitionStore
    completion_store: App\Sets\CompletionStore
    clock: clock
```

```sh
bin/console messenger:consume scheduler_tilttek_sets
```

## Requirements

PHP 8.5 or later, Symfony 8.2 and `tilttek/sets`; `symfony/console` for the command, `symfony/messenger` for the
handler, `symfony/scheduler` for the schedule and `symfony/lock` for its lock.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
