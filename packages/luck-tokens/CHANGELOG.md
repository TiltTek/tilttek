# Changelog

All notable changes to `tilttek/luck-tokens` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.2-alpha - 2026-11-25

### Changed

- `LuckTokens::eraseHolder()`, `LedgerEntry::pseudonymized()` and `InMemoryTokenLedger::eraseHolder()` clear the reason
  of the entries the member holds: a reason is written about its holder, and the audit of the balances needs only the
  changes. The reasons of the entries the member wrote for others stay. A `TokenLedger` of your own does the same.

## 0.1.1-alpha - 2026-11-20

### Fixed

- The limit of entries of a ledger is checked as each entry is built: `startSession()` for many holders stops at the
  first entry past it, rather than building every refresh before refusing them all.

## 0.1.0-alpha - 2026-11-15

### Added

- `LuckTokens`: tokens granted by a game master or, for a kind that allows it, by anyone; spent by their holder, by an
  actor who controls them or by a game master; passed from one holder to another; set at the start of a session by
  carry-over and refresh for the holders of the ledger and those given; a balance never under zero nor over its cap;
  operations named by the caller, sent again without writing more, an identifier reused with other values refused;
  appended after the last position read, read and checked again after a conflict, three times in all; a ledger full at
  100 000 entries; balances, the ledger and the entries of a holder for the audit; a member erased from every ledger
  under a pseudonym drawn for the erasure, `erased:` and 32 random hexadecimal digits, as holder and as actor, the
  balances, positions and order left as they were, a member erased already left alone.
- `TokenKind`, `HolderScope`, `Refresh`, `GrantPolicy` and `TableRules`: kinds of a key, a name, a cap up to 1 000, a
  scope, a refresh, a carry-over, who grants and whether they pass; 16 kinds and 16 game masters a table; read from
  plain values or JSON within 64 KiB and 6 levels, a field not known refused, and written back.
- `Ledger\LedgerEntry` and `Ledger\EntryType`: the entries of the ledger with their position, operation, type, kind,
  holder, change, balance, actor, time in UTC, reason and session; written and read back as plain values, checked; the
  entry with a pseudonym in place of a member, by `pseudonymized()`.
- `Receipt` and `Balance`: what an operation wrote and the balance it left, and the balance of a holder.
- `Event\TokensGranted`, `Event\TokensSpent`, `Event\TokensTransferred` and `Event\SessionStarted`, dispatched through
  PSR-14 once the entries are written, and `Event\HolderErased`, with the pseudonym and the number of entries
  rewritten, naming neither the member nor a table.
- `Contract\TokenRules`, `Contract\TokenLedger` — with `eraseHolder()`, the one rewriting of the ledger — and
  `Contract\HolderAccess`, with `Store\InMemoryTokenRules`, `Store\InMemoryTokenLedger` — its balances, holders and
  sessions indexed — and `Store\SelfAccess`.
- `Exception\LuckTokensException`, with `InvalidTokenRule`, `TokenRefused` and `LedgerConflict`.
- A fuzz target for the rules of a table, and benchmarks of a spending after 10 000 entries and of the start of a
  session.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
