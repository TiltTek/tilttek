# tilttek/activity

**What your members did, recorded once, so that points, badges and quests can all count it the same way.**

tilttek/activity keeps a journal of the members' gestures: *this member made this gesture, on this object, at this
moment*. The application records each gesture with an idempotency key, so that a listener that runs twice or a task
started again never counts it twice; it cancels a gesture that was undone — an order refunded, a comment removed — by
an entry of its own, never by rewriting the journal. Readers count what the journal holds by member, verb, object and
period, and projections keep their own totals up to date from it, then replay it when a rule changes. Erasing a member
deletes their gestures and forgets them as the object of others'. It does not award points, badges or levels, and it
never stores a text the member wrote: the packages that reward gestures read this journal, and a comment is referenced,
not copied.

It is for an application that rewards what its members do — a community site, a store's loyalty programme, a game
site — and wants every reward to come from one record that can be read again. `tilttek/activity-bundle` integrates it
with Symfony and keeps the journal in Doctrine DBAL; an application without Symfony takes the library alone and writes
its own `ActivityLog`, or uses the one in memory.

It is a plain PHP library whose only dependencies are `phil/resource`, the opaque reference of a member or an object,
and `psr/clock`, the clock that stamps each entry. Members are references the application gives, `member:44`: the
library never reads a name, an e-mail or an account.

The design is small: an `Activity` is a fact; a `Journal` records, cancels, reads and counts activities through an
`ActivityLog`; a `Filter` selects activities; a `Projection` keeps derived state, which a `Projector` hands the entries
it has not seen, with a checkpoint kept in `Checkpoints`.

## Status

| Part | State |
|---|---|
| `Activity`, `Verb`, `VerbPattern`, `IdempotencyKey`, `Context`: the fact and its validated parts | Implemented and tested |
| `Journal`: record once by key, cancel once, declared verbs, observers told of new entries | Implemented and tested |
| `ActivityLog` and `InMemoryActivityLog`: append, cancel, read through a filter, totals, erasure | Implemented and tested |
| `Filter`: member, verbs by pattern, object, period | Implemented and tested |
| Projections: `Projector` with checkpoints and batches, rebuild of a period, `LiveProjections`, `Projections` | Implemented and tested |
| Exceptions under one `ActivityException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Recording activity](doc/recording.md) | Activities, verbs, keys, context, cancellations, declared verbs and observers. |
| [Reading and counting](doc/reading.md) | Filters, positions, reads in order and totals. |
| [Projections](doc/projections.md) | Derived state kept up to date, checkpoints, batches, rebuilds and live projections. |
| [Erasure](doc/erasure.md) | What erasing a member removes and what it keeps. |
| [Writing a journal](doc/writing-a-journal.md) | The `ActivityLog` contract an application's storage implements. |
| [Extension points](doc/extension-points.md) | What an application gives the library: the journal, projections, checkpoints, observers, the clock. |
| [Exceptions](doc/exceptions.md) | `ActivityException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that records activity. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require tilttek/activity
```

```php
use Symfony\Component\Clock\NativeClock;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Filter;
use Tilttek\Activity\Journal;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Phil\Resource\ResourceReference;

$journal = new Journal(new InMemoryActivityLog(), new NativeClock());

$journal->record(Activity::of('game:812:player:44', 'member:44', 'game.played', $playedAt, 'game:812'));
$journal->record(Activity::of('game:812:player:44', 'member:44', 'game.played', $playedAt, 'game:812')); // counted once

$journal->totals(Filter::all()->member(ResourceReference::of('member', '44'))->verbs('game.*'))->count; // 1
$journal->cancel('game:812:player:44');
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
