# tilttek/rules-lookup

**Search the rules of a game system, SRD included, in the reader's language: every result shows the source and licence
it comes from.**

tilttek/rules-lookup finds the rule a player or a game master is looking for: the words they type, in their language,
narrowed to a system, a version and a category. A rule has an identifier — its system, the version of that system and
its key, `srd-5.2@5.2.1:condition.blinded` —, titles and texts by locale, a category, the rules it refers to, and the
source it comes from, with its licence and the attribution that licence asks for. The search ignores case and accents —
`aveugle` finds `Aveuglé` —, folded by Unicode normalisation (NFKD, nonspacing marks removed, lowercase); it reads the
text a reader of the locale sees, the last word typed stands for the words it starts, and a word in a title counts more
than a word in a text. A result always carries the notice of its source, and a page of results the notices of every
source it shows, as text or as an escaped HTML list.

The rules come from the systems of `tilttek/rpg-systems` — each term, attribute and named roll a rule, whatever data
package or document it was read from — or are built in code. They are kept and searched behind one port: the store
in memory, which indexes the folded words once and never changes afterwards, serves an application that reads its
rules at warm-up and the tests; a store in a database or a search engine is an adapter the application writes. When
the rules of a system change, the application sends `ReindexRules`, a message that carries the system and its version
only.

It is for an application that answers rules questions — a campaign tool, a game master's screen, a bot at the table —
and must show where each rule comes from. It is a plain PHP library whose dependencies are `tilttek/rpg-systems`, for
the systems, the provenances and the attribution, `symfony/polyfill-intl-normalizer`, which stands in for `intl` when
the extension is missing, and the `mbstring` extension.

## Status

| Part | State |
|---|---|
| `Rule`, `RuleId`: rules with their system, version, key, titles and texts by locale, category, references and source | Implemented and tested |
| `RuleQuery`: words folded without case nor accents, locale, system, version, category and page, with their bounds | Implemented and tested |
| `Contract\RuleStore`, `Store\InMemoryRuleStore`: the store port, and the store in memory with its scoring | Implemented and tested |
| `RulesLookup`, `SearchResults`, `RuleHit`: search, a rule by its identifier, references both ways, the attribution of every result | Implemented and tested |
| `RulesFromSystem`: the rules of a system of `tilttek/rpg-systems` | Implemented and tested |
| `Message\ReindexRules`: the message of a system whose rules changed | Implemented and tested |
| A store on Doctrine DBAL or on a search engine | Not started: an adapter of the application, or of a future bundle |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Rules](doc/rules.md) | Rules, their identifiers, their sources and references, and the rules of a system. |
| [Searching](doc/searching.md) | Queries, folding, scoring, pages, and the attribution of the results. |
| [Stores](doc/stores.md) | The store port, the store in memory, and writing a store of your own. |
| [Extension points](doc/extension-points.md) | What an application gives the library. |
| [Exceptions](doc/exceptions.md) | `RulesLookupException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests, its fuzz target and its benchmarks. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```php
use Tilttek\RpgSystems\Package\DataPackageReader;
use Tilttek\RulesLookup\RuleQuery;
use Tilttek\RulesLookup\RulesFromSystem;
use Tilttek\RulesLookup\RulesLookup;
use Tilttek\RulesLookup\Store\InMemoryRuleStore;

$system = new DataPackageReader()->load('data/open-rules');
$lookup = new RulesLookup(new InMemoryRuleStore(...new RulesFromSystem()->rules($system)));

$results = $lookup->search(new RuleQuery('aveugle', 'fr_CA', category: 'condition'));

foreach ($results->hits as $hit) {
    echo $hit->title, ': ', $hit->text, ' — ', $hit->attribution, "\n";
}

echo $results->attributionHtml();
```

## Requirements

PHP 8.5 or later, with `mbstring`. The `intl` extension is suggested: the folding is the same without it, through
`symfony/polyfill-intl-normalizer`, only slower.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE). The
licence covers the library's code; a rule keeps the licence its source states.
