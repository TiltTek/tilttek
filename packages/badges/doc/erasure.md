# Erasure

`Badges::erase($member)` removes everything the library keeps about the member and returns an `Erasure`:

| Field | What was removed |
|---|---|
| `awards` | Their awards, by hand or earned |
| `contributions` | What their activity brought to the badges' criteria |
| `grants` | The consents they gave other applications |
| `profile` | Whether a profile — the badges shown, the showcase, the title — was removed |

The member's activity itself is `tilttek/activity`'s, erased by its journal. A credential the application issued for
a badge is the member's: it stays verifiable where they keep it.
