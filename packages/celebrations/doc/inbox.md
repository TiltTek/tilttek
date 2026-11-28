# The inbox

Each member has one inbox. It holds entries, each an `Achievement` with the `sequence` the inbox received it under, its
`EntryState`, the moment it was received and the moment it was settled.

## Achievements

An `Achievement` names what a member earned without depending on the package that gave it:

| Field | What it holds | Bounds |
|---|---|---|
| `member` | The member's `ResourceReference`, `member:42` | — |
| `source` | The key of the package or feature that gave it: `badges`, `quests`, `progression` | A key |
| `kind` | What sort of reward it is in that source: `badge`, `tier`, `level`, `milestone`, `quest`, `set` | A key |
| `subject` | The identifier of what was earned in that source: `badge.first-roll`, `track.reader` | 1 to 191 visible ASCII characters, no space |
| `occurrence` | What makes it unique for that source and member: `badge.first-roll#1`, a second award `#2` | 1 to 191 visible ASCII characters, no space |
| `earnedAt` | The moment it was earned, which orders the inbox | — |
| `parameters` | What its message needs — `['level' => 5]` —, sorted by name | At most 10, each named by a key, an integer or 1 to 100 characters of UTF-8 |
| `showUntil` | The moment after which it is no longer worth showing, or none | After `earnedAt` |

A key is 1 to 96 lowercase letters, digits, `_`, `-`, or single dots between them; the subject and the occurrence take
the rewards package's own codes as they are. The application turns the source, the kind and the subject into a message
through its translations, `celebrations.badges.badge` with the badge's name, and so keeps every text out of the inbox.

## Receiving once

`receive()` keeps an achievement in its member's inbox, pending, and returns its `Entry`. An achievement told again —
the same member, source and occurrence — is kept once: `receive()` returns the entry kept, in the state it stands in
now, and tells nothing. The rewards packages tell their observers at least once, so a notice may come twice; the
inbox makes that harmless.

An achievement whose `showUntil` has already passed when it arrives is kept lapsed, for the history, and never shown.

## Groups and order

`pending()` returns the member's pending entries in `Group`s of one source and one kind. Inside a group, and between
groups, the earliest earned comes first, ties in the order the inbox received them; a group comes where its earliest
entry comes. A group shows its first entries — 10 by default, `Limits::$shownPerGroup` — and counts them all: `count`,
`hidden()` for those beyond the first, `isSingle()` for a group of one, shown alone rather than as "1 new badge".

`count()` returns how many entries wait to be shown, for the bell.

## Seen

The member's page marks entries seen once the window showed them:

| Method | Marks seen |
|---|---|
| `markSeen($member, $sequences)` | The pending entries of these sequences, `$group->sequences()` — at most as many sequences as `Limits::$pendingPerMember` |
| `markGroupSeen($member, $source, $kind)` | Every pending entry of that source and kind, those the group did not show too |
| `markAllSeen($member)` | Every pending entry |

Each returns how many entries it marked. A sequence that is not pending — seen already, withdrawn, lapsed, unknown —
is passed over, so a page sent twice is harmless.

## Withdrawn

`withdraw($member, $source, $occurrence)` settles an entry whose reward was taken back — a badge revoked, a level lost
after a cancelled activity —: pending, it is never shown; seen, the history shows it withdrawn. It returns false, and
does nothing, for an entry the inbox does not hold or holds withdrawn already. An achievement received again after its
withdrawal stays withdrawn: a reward given anew comes with a new occurrence — `#2` —, as the rewards packages number
their awards.

## Lapsed

An entry lapses — its reward still stands — in two cases:

- its `showUntil` came while it waited: it is read lapsed from that moment, by `pending()`, `count()`, `next()` and
  `history()`, and settled lapsed in the store when a later pending achievement arrives for that member;
- the member's pending entries reached their bound — 200 by default, `Limits::$pendingPerMember` — when another
  arrives: the earliest earned lapse, so that one more fits.

## Bounds and history

The inbox keeps at most `Limits::$entriesPerMember` entries — 500 by default. When a new entry goes beyond, the first
received of the settled entries — seen, withdrawn or lapsed — are dropped; pending entries never are, as their bound is
never above this one.

`history($member, $limit, $before)` reads a page of the inbox in every state, the last received first: 1 to 100
entries, 50 by default, those received before the sequence `$before` — the sequence of the last entry of the previous
page — when it is given.

Sequences are given per member and never reused, even after an entry is dropped or the member forgotten.

## Erasure

`forget($member)` removes the member's inbox and preferences. The rewards themselves live in their own packages, which
erase them by their own means.
