# Privacy

## What stays anonymous

The screens show what the table agreed, its pact, its check-in tally and the summary of a session's stars and wishes,
none of which says who gave which answer. The stars and wishes are shown in whole groups of `feedback_threshold`
participants, 3 by default: under it, the game master reads only that there are not enough answers yet. The players
see what the table agreed and the pact in whole groups of the library's answers threshold, 3 by default; the game
master sees the lines, the veils and the checklist from the first answer, still without their authors, to keep them
out of the game. An anonymous
signal has no author in the database, on the screens or on the hub. A participant's own answers are shown to them alone.
The printable cards are blank: they list the table's themes and print nobody's answers.

## Export and erasure

With `phil/privacy-bundle` installed, `TableSafetyPrivacy` is tagged as an exporter and an eraser, on the contracts of
`Phil\PrivacyBundle\Application\Contract`. The data subject is a participant, referenced under
`privacy.participant_kind` (`member` by default).

| Store | Export | Erasure | Why |
|---|---|---|---|
| `tilttek_table_safety_participation` | the answers at each table | deleted | The answers say what the participant will not play and how far they consent: the most sensitive data the bundle holds. The agreement of the table is computed again from the answers left. |
| `tilttek_table_safety_feedback` | the stars and wishes of each session | deleted | The summary of a session is computed again from the stars and wishes left, in whole groups of `feedback_threshold`. |
| `tilttek_table_safety_signal` | the named signals they raised | redacted: kept without the name | A signal serves the game master during play — one not yet seen, or the answers to the last check-in, would be lost to the table if it vanished — and the purge deletes it after `retention`. Its author is cleared, so it becomes an anonymous signal; the report says `redacted` rather than `anonymised`, since its table, its kind and its moment may still point to the participant at a small table until the purge. |

The settings of a table, `tilttek_table_safety_table`, name nobody and are not in the report. The erasure may run again
on an erased participant and reports the same.

## The purge of signals

A signal serves during play. `tilttek:table-safety:purge-signals` deletes those older than `retention` (30 days by
default), or than the retention it is given:

```sh
php bin/console tilttek:table-safety:purge-signals
php bin/console tilttek:table-safety:purge-signals --retention="7 days"
```

Run it from a cron or Symfony Scheduler.
