# Changelog

All notable changes to `tilttek/luck-tokens-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.2-alpha - 2026-11-25

### Added

- `DbalTokenRules::removeGameMaster()`, which takes a member off the game masters of every table, by pages of 500
  tables.

### Changed

- `LuckTokensPrivacy` takes the rules kept in the database as an optional fourth argument, given by the bundle with
  `rules: doctrine`: the erasure takes the member off the game masters of every table, and the report names
  `tilttek_luck_tokens_rules.game_masters` as deleted. The reason of each choice is in
  [Erasing a member](doc/erasing-a-member.md).
- `DbalTokenLedger::eraseHolder()` clears the reason of the entries the member holds, and the report of
  `LuckTokensPrivacy` names `tilttek_luck_tokens_entry.reason` as deleted; the holder and the actor stay pseudonymised.
- The development requirements accept `phil/privacy-bundle` 0.3, the version published on 2026-11-24, and no earlier
  one, so that the package installs on its own beside the published version of that bundle.

## 0.1.1-alpha - 2026-11-24

### Changed

- The development requirements accept `phil/privacy-bundle` 0.2, its latest published minor version, and no earlier
  one, so that the package installs on its own beside the published version of that bundle.
- The tests: a process of the concurrency test waits longer after each conflict in a row before it tries again, up
  to 64 ms, so that a writer that holds the SQLite write lock and is paused by a busy machine commits before the
  others run out of attempts.
- The documentation in French: the lines of the README and of the page on testing fit in 120 columns.
- Requires the latest minor of each dependency: `doctrine/orm` 3.8, `doctrine/dbal` 4.6, `doctrine/doctrine-bundle` 3.4.

## 0.1.0-alpha - 2026-11-20

### Added

- `TilttekLuckTokensBundle` and its configuration `tilttek_luck_tokens`: the store of the ledgers, Doctrine DBAL or
  memory, and its connection; the rules of the tables from the configuration, the database or a service of the
  application, the configured tables checked when the container is compiled; the clock; the size of a ledger; who
  controls the tokens of whom — each holder their own, the security voters, or a service —; the locks; the kind of a
  member for the privacy bundle.
- `LuckTokens` registered with the rules, the ledger, the application's event dispatcher, the access and the clock, so
  that the events reach the application's listeners.
- `Infrastructure\Doctrine\DbalTokenLedger`: the ledgers in `tilttek_luck_tokens_entry`, the table and the position
  the primary key, the entries of an operation inserted in one transaction after the last position is read again
  there, another writer at the same position, a deadlock or a lock timeout answered by `LedgerConflict`; each balance
  read from the index of the holder's last entry of the kind, the entries of an operation from an index ordered by
  position; a member erased under a pseudonym by two updates in one transaction.
- `Infrastructure\Doctrine\DbalTokenRules`: the rules of each table as JSON in `tilttek_luck_tokens_rules`, saved,
  removed and read back checked.
- `Infrastructure\Doctrine\LuckTokensTables` and `LuckTokensSchemaListener`: the tables added to the schema the ORM
  generates for their connection, the rules' table only when the rules are kept there; on MySQL and MariaDB, the texts
  compared byte by byte.
- `Infrastructure\Rules\ConfiguredTokenRules`: the rules of the tables of the configuration, read once.
- `Application\LockedLuckTokens` and `Application\LockScope`: the operations under a Symfony lock — of each holder,
  both holders of a transfer in one order, or of the whole table; the table for a session, the member for an erasure
  —, released even when the operation fails.
- `Infrastructure\Security\SecurityHolderAccess`, `HolderControlVoter` and `HolderControl`: the access of actors to
  holders asked of the security voters, the bundle's voter granting each holder their own tokens and abstaining
  otherwise.
- `Infrastructure\Privacy\LuckTokensPrivacy`: the erasure of a member for `phil/privacy-bundle`, under their lock when
  the locks are enabled, reported as pseudonymised.
- The stores' tests on SQLite, MySQL 8.4 and PostgreSQL 17, four processes granting at once with and without the
  locks, and a benchmark of a spending after 10 000 entries in the database.
- The Flex recipe in `recipe/0.1`: the bundle and `config/packages/tilttek_luck_tokens.yaml`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
