# The item of the day

```yaml
tilttek_collections:
    timezone: America/Toronto
    daily:
        enabled: true
        verbs: ['page.viewed', 'member.signed_in']
        sets: [crew]
        dates: { '10-31': pumpkin }
        computed: [{ rule: easter, item: egg }]
```

With `enabled`, the projection `collections.daily` is tagged for `tilttek/activity-bundle`: the first activity of a
member on a day, among the `verbs`, gives them the item of that day. Catch it up and rebuild it with the journal's
commands:

```sh
bin/console tilttek:activity:catch-up collections.daily
bin/console tilttek:activity:rebuild collections.daily --since=2026-11-01
```

A day's item is the one kept for it, else the one of the first fixed date, else the one of the first computed date —
`computed` takes the rules of `phil/almanac` and how many days the item stays —, else a draw weighted by rarity among
the items of `sets`, from the day and the secret `salt`.

`tilttek:collections:daily` generates the calendar ahead and shows it; correct a day with
`DailyCalendar::choose(day, item)` and give it back to the calendar with `release(day)`. In a template,
`tilttek_collections_today()` returns the item of today and `@TilttekCollections/daily.html.twig` shows it.
