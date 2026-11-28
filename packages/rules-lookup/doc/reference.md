# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Rule` | A rule: identifier, titles and texts by locale, category, references and source |
| `RuleId` | The identifier of a rule: system, key and version, written `system@version:key` |
| `RuleQuery` | A search: folded words, locale, system, version, category and page |
| `RuleMatch` | A rule a store found, with its score |
| `RuleMatches` | One page of matches and their total |
| `RuleHit` | A rule as a reader sees it in the results, with the notice of its source |
| `SearchResults` | One page of hits, and the notices of its sources as text or HTML |
| `RulesLookup` | Searches, finds a rule by its identifier, and follows references both ways |
| `RulesFromSystem` | The rules of a system of `tilttek/rpg-systems` |
| `Contract\RuleStore` | The store port: get, search, and the rules that refer to one |
| `Store\InMemoryRuleStore` | The store in memory, indexed once, never changed |
| `Message\ReindexRules` | The message of a system whose rules changed, with its key and version |
| `Exception\RulesLookupException` and its two exceptions | [Exceptions](exceptions.md) |
