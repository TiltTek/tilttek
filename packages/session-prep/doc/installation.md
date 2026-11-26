# Installation

```sh
composer require tilttek/session-prep
```

The package needs PHP 8.5 or later, with `ext-mbstring`, which counts the characters of the texts.

An application gives `SessionPreparer`:

- a `Contract\PreparationStore` that keeps the preparations in its database;
- a `Contract\PreparationObserver`, to send its messages, or nothing.

The service keeps no state between calls, so one instance serves every request of a long-running worker.
`Store\InMemoryPreparationStore` keeps the preparations in memory, for tests.
