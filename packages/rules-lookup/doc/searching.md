# Searching

## A query

```php
$query = new RuleQuery(
    'épée longue',          // the words looked for; empty to list a category
    'fr_CA',                // the reader's locale
    system: 'srd-5.2',
    version: '5.2.1',
    category: 'weapon',
    limit: 20,
    offset: 0,
);

$results = $lookup->search($query);
```

| Argument | Bounds |
|---|---|
| `text` | UTF-8, at most 200 characters and 16 words; empty only with a category |
| `locale` | `fr`, `fr_CA`, `fr-ca` or `es-419`, kept normalised |
| `system`, `category` | Keys, or null for every one |
| `version` | A version, or null for every one |
| `limit` | 1 to 100 results on a page |
| `offset` | 0 to 10 000 |

A query that steps out of its bounds is refused with an `InvalidQuery` when it is made, before any store reads it.

## Folding

The words of the query and of the rules are folded the same way: decomposed by NFKD, their nonspacing marks (`\p{Mn}`)
removed, lowercased and recomposed by NFC — `Épée`, `EPEE`, `épée` and an `e` followed by a combining acute accent are
one word; `ﬁ` reads `fi`, `Ａ` reads `a`. The normalisation is the `Normalizer` of `intl` when the extension is loaded,
and `symfony/polyfill-intl-normalizer` otherwise, which gives the same words. A text in plain ASCII is only lowercased,
and a text that is not valid UTF-8 has no words.
A word is a run of letters and digits in any script: `1d20` and `Ελληνικά` are words, punctuation separates them.

## What a search reads

A rule answers a query when every word is found in the title or the text a reader of the query's locale sees: the
text of the locale, of its language, in English, then the first text the rule holds. A reader in `fr_CA` finds
`Aveuglé` by `aveugle`; a reader in English does not, since the English title is `Blinded`.

The last word typed also stands for the words it starts, from two characters: `blin` finds `Blinded`, as the reader
types it. The other words are whole words.

## Scoring

In the store in memory, each word scores:

| Where the word is | Points |
|---|---|
| A word of the title | 10 |
| The start of a word of the title (the last word only) | 6 |
| A word of the text | 3 |
| The start of a word of the text (the last word only) | 1 |

A title equal to the words searched adds 100, a title that starts with them 20. The results come best first; ties
go to the title in alphabetical order of its folded words, then to the identifier. A query with a category and no
words lists the category by title, each with a score of 0.

## Results

`search()` gives `SearchResults`, one page of `RuleHit`:

```php
$results->total;              // how many rules answer, on every page
$results->hasMore();          // whether a page follows
$hit = $results->hits[0];
$hit->title;                  // in the reader's locale
$hit->text;
$hit->score;
$hit->attribution;            // the notice of the rule's source
```

## Attribution

Every result carries the notice of its source — its attribution, or its title, then its licence and the page of the
licence's text — even when the licence asks for none, so that a page never shows a rule without where it comes from.
A page of results gives the notices of every source it shows, each once:

```php
$results->sources();           // the provenances
$results->attributionText();   // one line per source
$results->attributionHtml();   // <ul class="attribution">, every text escaped, rel="license" on the licence
```

`attributionHtml()` is an empty string for a page without results.
