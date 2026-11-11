# Exceptions

The bundle raises the exceptions of `tilttek/collections`, under its `CollectionsException` interface:

| When | Exception | Message, for instance |
|---|---|---|
| The container is compiled with a wrong catalog | `InvalidCollection`, `NotFound` | `No rarity is named "rare".` |
| The timezone is none | `InvalidCollection` | `"Mars/Olympus" is not a timezone.` |
| A computed date needs `phil/almanac`, absent | `InvalidCollection` | `The computed dates of the item of the day need phil/almanac.` |
| A computed date cannot be read | `InvalidCollection` | `"someday" is not a rule the almanac reads.` |
| A definition in the database is not JSON | `InvalidCollection` | `The definition of the rarity "common" is not JSON: Syntax error.` |

A wrong configuration value — an unknown store, an empty connection, an item of the day enabled without verbs — is
refused by Symfony's configuration with an `InvalidConfigurationException`. A column that holds an unexpected value
raises an `UnexpectedValueException`. A trade refused by the collections reaches `tilttek/exchange` as its
`ExchangeRefused` with `NotOwned`.
