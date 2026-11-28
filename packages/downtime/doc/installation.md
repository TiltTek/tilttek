# Installation

```sh
composer require tilttek/downtime
```

The package needs PHP 8.5 or later, with `ext-mbstring`, which counts the characters of the texts. It requires no other
package.

An application gives `Downtime`:

- a `Contract\EntryStore` that keeps the entries in its database;
- a `Contract\RuleBook`: `Rules\InMemoryRuleBook` over the `SystemRules` the application declares once, or a rule book
  of its own;
- a `Contract\Roster` that says who runs each campaign and who plays each character;
- a `Contract\Roller` that rolls the dice expressions of its systems, or nothing, to record the rolls made at the
  table;
- a `Contract\DowntimeObserver`, to send its messages, or nothing.

The service keeps no state between calls, and the rules are plain data, so one instance serves every request of a
long-running worker. `Store\InMemoryEntryStore` keeps the entries in memory, for tests.
