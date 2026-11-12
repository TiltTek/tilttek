# Installation

## Requirements

- PHP 8.5 or later.
- `phil/resource`, the opaque reference of an author.
- `psr/clock`, the clock the author's today is read from, and `psr/event-dispatcher`, which tells the application of a
  goal reached. A Symfony application passes its `clock` and `event_dispatcher` services.

## Installing

```sh
composer require tilttek/writing-goals
```

## Wiring the library by hand

Two stores, a clock and a dispatcher make `WritingGoals`:

```php
use Symfony\Component\Clock\Clock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tilttek\WritingGoals\Store\{InMemoryGoalStore, InMemoryLogStore};
use Tilttek\WritingGoals\WritingGoals;

$goals = new WritingGoals(new InMemoryGoalStore(), new InMemoryLogStore(), new Clock(), new EventDispatcher());
```

`Planner` computes a plan from a goal and its progress with no store at all, for an application that keeps both
itself:

```php
$plan = new Planner()->plan($goal, Progress::fromArray(['2026-11-01' => 1800]), Day::fromString('2026-11-02'));
```

The stores in memory forget everything with the process: an application writes its own on its storage
([Writing a store](writing-a-store.md)).
