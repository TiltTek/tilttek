# Installation

## Requirements

- PHP 8.5 or later, with `mbstring`.
- `symfony/polyfill-intl-normalizer`, which normalises the text the search folds when `intl` is missing. The `intl`
  extension is suggested: it gives the same words, about ten times faster on a long accented text.
- `tilttek/rpg-systems`, which gives the systems, the provenances and the attribution.

## Installing

```sh
composer require tilttek/rules-lookup
```

## Wiring the library

The library has no service to configure. An application builds its store once — at warm-up, or when its cache is
rebuilt — and gives it to `RulesLookup`:

```php
$rules = [];

foreach ($systems->all() as $system) {
    array_push($rules, ...new RulesFromSystem()->rules($system));
}

$lookup = new RulesLookup(new InMemoryRuleStore(...$rules));
```

`InMemoryRuleStore` never changes once made, and `RulesLookup` keeps nothing between two calls: one instance serves
every request, in a worker as in a request. Building the store indexes every word of every rule; for 5 000 rules in two
languages it takes about 0.25 s ([Testing](testing.md#benchmarks)), so an application builds it once rather than at
each request.
