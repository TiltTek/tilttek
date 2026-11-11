# Exceptions

Every exception of the library implements `Tilttek\Progression\Exception\ProgressionException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidProgression` | A name, a curve, a rule, a cap, a season, a milestone, a grace, a timezone or a date is refused | `"Player" is not a track name: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.` |
| `UnknownTrack` | A track is asked for by a name none has | `No track is named "author".` |
| `UnknownStreak` | A streak is asked for by a name none has | `No streak is named "monthly".` |

The verb patterns and the context conditions of a rule are checked by `tilttek/activity`, which raises its own
`InvalidActivity`.
