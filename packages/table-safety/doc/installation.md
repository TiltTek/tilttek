# Installation

```sh
composer require tilttek/table-safety
```

The library needs PHP 8.5 or later with `ext-mbstring` and `ext-sodium`, `phil/content-warnings` for the themes and
`phil/resource` for the references of tables and participants. It has no other dependency.

`TableSafety` takes a catalogue of themes — `BaseCatalogue::load()` or the application's own —, the four stores of
[Writing a store](writing-a-store.md), and optionally an observer of the signals and the settings of a table that chose
nothing. The memory stores serve the tests; an application keeps the answers in a database, as
`tilttek/table-safety-bundle` does with Doctrine DBAL.
