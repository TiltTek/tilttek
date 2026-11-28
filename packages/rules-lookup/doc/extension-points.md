# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| `Contract\RuleStore` | A store of its own, in a database or a search engine | [Stores](stores.md#a-store-of-your-own) |
| `Rule` | Rules built in code, from a source `RulesFromSystem` does not read | [Rules](rules.md) |
| `Message\ReindexRules` | The handler that indexes a system's rules again in its own store | [Stores](stores.md#a-store-of-your-own) |
| `AttributionRenderer` | The renderer of `tilttek/rpg-systems` given to `RulesLookup`, for its notices | [Searching](searching.md#attribution) |

The rules, identifiers and queries are values. The services keep no state between two calls.
