# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/rules-lookup` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `RuleId` and `Rule`: rules identified by their system, key and version — written `srd-5.2@5.2.1:condition.blinded`
  —, with titles and texts by locale, a category, at most 64 references to rules of the same system and version, and
  the provenance of `tilttek/rpg-systems` that names their source, licence and attribution.
- `RuleQuery`: a search by words, locale, system, version and category, one page of 1 to 100 results; the words folded
  without case nor accents — NFKD, nonspacing marks removed, lowercase, NFC — by the `Normalizer` of `intl` or of
  `symfony/polyfill-intl-normalizer`, `intl` being suggested; at most 200 characters and 16 words.
- `Contract\RuleStore` and `Store\InMemoryRuleStore`: the store port, and a store in memory of at most 100 000 rules
  that indexes their folded words once and never changes afterwards; a search that reads the text a reader of the
  locale sees, the last word standing for the words it starts, scored by where each word is found, best first, ties to
  the title then to the identifier.
- `RulesLookup`, `SearchResults` and `RuleHit`: the search, a rule by its identifier, the rules one refers to and those
  that refer to it, each result with the notice of its source, and a page with the notices of its sources as text or
  as an escaped HTML list.
- `RulesFromSystem`: the terms, attributes and named rolls of a system of `tilttek/rpg-systems` as rules, each with its
  record's source.
- `Message\ReindexRules`: the message of a system whose rules changed, with its key and version only.
- Exceptions under one `RulesLookupException` interface: `InvalidRule` and `InvalidQuery`.
- A fuzz target of the queries and benchmarks of building a store and searching it.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
