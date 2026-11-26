# Installation

```sh
composer require tilttek/conditions
```

The package needs PHP 8.5 or later and `psr/clock`, whose clock gives the service the time.

An application gives `Conditions`:

- a `Contract\ConditionStore` that keeps each character's conditions in its database;
- the `ConditionCatalogue` of its game system, built once from its system data;
- a PSR-20 `ClockInterface`, such as the `NativeClock` of `symfony/clock`;
- a `Contract\ConditionObserver`, to send its messages, or nothing;
- an `Effect\EffectCalculator` with the rounding of its system, or the default, which rounds down.

The service, the catalogue and the calculator keep no state between calls, so one instance of each serves every request
of a long-running worker.

`Store\InMemoryConditionStore` keeps the conditions in memory, for tests.
