# Installation

## Requirements

- PHP 8.5 or later.
- `phil/resource`, the opaque reference of a member or an object (`member:44`, `game:812`).
- `psr/clock`, the interface of the clock that stamps each entry; any PSR-20 clock does, such as `symfony/clock`.

## Installing

```sh
composer require tilttek/activity
```

Nothing is registered and nothing is configured: build a `Journal` over an `ActivityLog` where you use it. A Symfony
application installs `tilttek/activity-bundle`, which keeps the journal in Doctrine DBAL and builds the `Journal`, the
`Projector` and the projections from its configuration.

```php
use Tilttek\Activity\Journal;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\Activity\Verbs;

$journal = new Journal(new InMemoryActivityLog(), $clock, Verbs::of(['news.commented', 'game.*']));
```

`InMemoryActivityLog` forgets everything with the process: it is for tests, scripts and examples. An application that
keeps its journal writes an `ActivityLog` on its storage ([Writing a journal](writing-a-journal.md)) or installs the
bundle.
