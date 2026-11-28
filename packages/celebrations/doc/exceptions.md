# Exceptions

Every exception of the library implements `Exception\CelebrationException`: catch this one to catch them all.

| Exception | Thrown when | Message |
|---|---|---|
| `InvalidCelebration` | A key, an occurrence, a parameter, a moment, a sequence, a group, a page size, a list of sequences or a limit out of bounds | What is out of bounds, and the bound |

`InvalidCelebration` extends `InvalidArgumentException`. Its messages:

| Value | Message |
|---|---|
| A source, kind or parameter name | `The key of a source must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them; "…" is not.` |
| A subject or an occurrence | `The subject of an achievement is 1 to 191 visible ASCII characters, without spaces.`, and likewise for the occurrence |
| Too many parameters | `An achievement carries at most 10 parameters; this one has ….` |
| A parameter's text | `The parameter "…" is 1 to 100 characters of UTF-8 without control characters nor surrounding spaces.` |
| A `showUntil` not after `earnedAt` | `The moment an achievement stops being shown comes after the moment it was earned.` |
| A sequence below 1 | `The sequence of an entry starts at 1; … is not one.` |
| A pending entry with a settling moment, or a settled one without | `A pending entry has no settling moment, and a settled entry has one.` |
| Settling an entry as pending | `An entry is settled as seen, withdrawn or lapsed, never as pending.` |
| An empty group | `A group shows at least one entry.` |
| A group's entry of another source or kind | `A group holds the entries of one source and one kind; sequence … is not of "…" and "…".` |
| A group counting fewer entries than it shows | `A group counts at least the … entries it shows; … is fewer.` |
| Too many sequences marked seen at once | `At most … entries are marked seen at once; … were given.` |
| A history page | `A page of the history holds 1 to 100 entries; … is out of bounds.` |
| A limit | `The limit of pending entries per member is 1 to 1000; it is ….`, and likewise for the others |

The exceptions of `phil/resource` reach the caller unchanged when a reference is malformed.
