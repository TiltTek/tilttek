# Exceptions

Every exception of the library implements `Tilttek\RulesLookup\Exception\RulesLookupException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidRule` | A rule, an identifier, a reference, a store or a message is refused when it is made: a key, a version, a title missing, a reference to itself, two rules with one identifier | `The rule "a:b" refers to itself.` |
| `InvalidQuery` | A query steps out of its bounds: text too long or not UTF-8, too many words, no words nor category, a locale, a filter or a page refused | `A search holds at most 16 words.` |

The texts of a rule are `Translations` of `tilttek/rpg-systems`, which refuse a text of their own with its
`InvalidRecord`.
