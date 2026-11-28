# Stores

## The port

`Contract\RuleStore` is where the rules are kept and searched:

| Method | Gives |
|---|---|
| `get(RuleId $id): ?Rule` | The rule of an identifier, or null |
| `search(RuleQuery $query): RuleMatches` | One page of the rules that answer the query, best first, each with its score, and their total |
| `referencing(RuleId $id): array` | The rules that refer to a rule |

`RulesLookup` reads the store only through these three methods.

## The store in memory

`Store\InMemoryRuleStore` holds the rules given to it, at most 100 000, and indexes them once: the folded words of
every title and text by locale, the rules that hold each word, the words sorted for the start of a word, and the rules
that refer to each rule. It scores as [Searching](searching.md#scoring) says.

```php
$store = new InMemoryRuleStore(...$rules);
$larger = $store->with(...$moreRules);   // a new store; a rule of the same identifier is replaced
$store->all();                           // the rules, in their order
```

The store never changes once made: a worker shares it between requests, and `with()` gives a new store. Two rules with
one identifier are refused with an `InvalidRule`.

A search reads only the rules that hold every word in some title or text, found by the index, before scoring them in
the reader's locale. The start of a word stands for at most 64 words of the store.

## A store of your own

A store in a database — on Doctrine DBAL — or in a search engine is an adapter the application writes, or a future
bundle provides: it implements `Contract\RuleStore`, folds the words of the rules as the library does, and keeps every
rule's provenance with it, so that each result still carries its attribution. The query it receives is already
checked and folded: `RuleQuery::$words` and `RuleQuery::phrase()` are what it searches for.

When the rules of a system change — a data package imported or updated —, the application sends
`Message\ReindexRules` with the system's key and version, and the handler of its own store reads the rules again
and indexes them:

```php
$bus->dispatch(new ReindexRules('srd-5.2', '5.2.1'));
```

The message carries identifiers only: the handler reads the rules itself.
