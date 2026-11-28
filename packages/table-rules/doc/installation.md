# Installation

```sh
composer require tilttek/table-rules
```

The package needs PHP 8.5 or later, `psr/clock`, whose clock gives the service the time, and `tilttek/rpg-systems`,
whose game systems give the rule variants a version may choose.

An application gives `TableRules`:

- a `Contract\RulebookStore` that keeps each table's rulebook and every version published in its database;
- a PSR-20 `ClockInterface`, such as the `NativeClock` of `symfony/clock`;
- a `Contract\TableRulesObserver`, to send its messages, or nothing;
- a `Contract\VariantCatalogue` of the variants its game systems offer — `Catalogue\SystemVariantCatalogue` reads those
  of its `tilttek/rpg-systems` systems —, or nothing, to take any identifier as a variant;
- a `Diff\RulebookDiffer`, or the default.

The service and the comparer keep no state between calls, so one instance of each serves every request of a
long-running worker.

`Store\InMemoryRulebookStore` keeps the rulebooks in memory, for tests.
