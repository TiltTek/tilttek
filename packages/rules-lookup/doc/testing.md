# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network: folding — precomposed and decomposed accents, compatibility
forms, case beyond ASCII, Hangul recomposed, spacing marks kept, text that is not UTF-8 —, searches with accented and
cased queries, keys and locales, identifiers, rules and their refusals, queries and their bounds, the store in memory —
scoring, the start of a word, the reader's locale, filters, pages, ties, references, replacing rules —, the rules of a
system, the search with the attribution of every result, and the reindex message. In the monorepo, Infection holds the
library at 100 % of covered mutants.

`tests/Fuzz/query.php` is the fuzz target of the queries: any text is refused with the library's exceptions, or gives
words that fold into themselves and a page within its bounds. `composer fuzz` runs it.

## Benchmarks

`tests/Benchmark/SearchBench.php` measures a store of 5 000 rules in English and French, each text 60 words of a
vocabulary of 300. On PHP 8.5 with `intl` and without an opcode cache: building the store 0.23 s, a search of two words
0.14 ms, a search ending with the start of a word 3.7 ms, folding an accented query 4 µs, listing a category 2.1 ms.

## Testing code that searches rules

Build a few rules, or the rules of a small system, and a store in memory:

```php
$source = new Provenance('Test', Licence::Owned);
$lookup = new RulesLookup(new InMemoryRuleStore(
    new Rule(new RuleId('test', 'condition.blinded'), Translations::of(['en' => 'Blinded']), $source),
));
```
