# tilttek/activity-bundle

**The members' activity of a Symfony application, recorded once in the database, for every reward to read.**

tilttek/activity-bundle integrates `tilttek/activity` with Symfony. It keeps the journal and the projections'
checkpoints in two tables on a Doctrine DBAL connection, adds them to the schema the ORM generates so that the
application's migrations create them, and writes each activity once per idempotency key even when two processes record
it at the same moment. It builds the `Journal` with the application's clock and declared verbs, finds the projections
and the observers by the interfaces they implement, keeps the projections up to date as the journal is written — in the request, or out of it
through Symfony Messenger — or by a command, rebuilds a projection after a rule changed, and answers the export and erasure requests of
`phil/privacy-bundle` for a member's activity. It awards nothing by itself: the packages that reward gestures, and the
application's own projections, read the journal.

It is for a Symfony application that records what its members do and rewards it with points, badges, levels, quests or
leaderboards, from one record that can be replayed.

It requires Symfony's DependencyInjection and Config, Doctrine DBAL, `tilttek/activity`, `phil/resource` and
`psr/clock`. The ORM, the Console, Messenger and `phil/privacy-bundle` are optional: the schema listener, the commands,
the projections' messages and the privacy module are registered only when they are installed.

The design follows the other bundles of the monorepo: an `AbstractBundle` with its configuration, services declared by
`ActivityServices`, a DBAL store and a store in memory for tests, tables added by a schema listener.

## Status

| Part | State |
|---|---|
| Configuration `tilttek_activity`: store, connection, verbs, batch size, how projections follow the journal, member kind | Implemented and tested |
| `DbalActivityLog` and `DbalCheckpoints`, with the tables `tilttek_activity_entry` and `tilttek_activity_checkpoint` | Implemented and tested |
| The tables in the schema the ORM generates, for the migrations | Implemented and tested |
| Projections and observers autoconfigured; live projections | Implemented and tested |
| Projections through Messenger: a message per projection and entry, the journal as outbox, the checkpoints as inbox | Implemented and tested |
| Commands `tilttek:activity:catch-up`, `tilttek:activity:rebuild`, `tilttek:activity:status` | Implemented and tested |
| Export and erasure of a member for `phil/privacy-bundle` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, enabling the bundle, the migration. |
| [Configuration](doc/configuration.md) | Every key of `tilttek_activity`, with its default. |
| [The journal in the database](doc/database.md) | The two tables, the idempotent insertion per platform, dates, positions. |
| [Recording activity](doc/recording.md) | Recording from a listener or a handler, cancelling, declared verbs. |
| [Projections and commands](doc/projections.md) | Writing a projection, live projections, projections through Messenger, catch-up, rebuild and status. |
| [Privacy](doc/privacy.md) | The export and the erasure of a member's activity. |
| [Extension points](doc/extension-points.md) | The interfaces and tags the application implements. |
| [Exceptions](doc/exceptions.md) | What the bundle raises. |
| [Testing](doc/testing.md) | The bundle's tests and how to test an application that records activity. |
| [Reference](doc/reference.md) | Every class of the bundle, with its role. |

## Quick start

```sh
composer require tilttek/activity-bundle
```

```yaml
# config/packages/tilttek_activity.yaml
tilttek_activity:
    verbs: ['news.commented', 'game.*', 'order.paid']
```

```php
use Tilttek\Activity\Activity;
use Tilttek\Activity\Journal;

final readonly class OnCommentPosted
{
    public function __construct(private Journal $journal) {}

    public function __invoke(CommentPosted $event): void
    {
        $this->journal->record(Activity::of('comment:'.$event->id, 'member:'.$event->authorId, 'news.commented', $event->postedAt, 'news:'.$event->newsId));
    }
}
```

Then `bin/console doctrine:migrations:diff` writes the migration of the two tables.

## Requirements

PHP 8.5 or later, Symfony 8.2, Doctrine DBAL 4.5.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
