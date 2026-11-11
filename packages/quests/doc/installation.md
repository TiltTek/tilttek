# Installation

```sh
composer require tilttek/quests
```

The library needs PHP 8.5 or later, `tilttek/activity` — the journal its steps read —, `phil/resource` and `psr/clock`.
It has no other dependency, and no gamification package: points, badges or items a quest rewards are given by the
application.

To use it, an application:

1. builds a `Catalog` — `InMemoryCatalog::fromArray()` over its configuration, or a catalog of its own over a
   database;
2. implements the four stores of [Writing a store](writing-a-store.md), or takes those in memory for tests and scripts;
3. builds an `Evaluator` over the catalog and the stores, then `Quests`, `Duels` and a `QuestProjection`, giving the
   last two a `QuestObserver` that gives the rewards;
4. registers the projection with the `Projector` of `tilttek/activity`, live through `LiveProjections` or by a task
   that catches it up.

In a Symfony application, `tilttek/quests-bundle` does all of this.
