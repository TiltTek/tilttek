# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network, on the ledger and the journal in
memory and a mock clock: an admission and its first times, keys and contexts; a scan dispatched again, recorded once; a
re-entry that adds no first time; the days and ISO weeks of the event's timezone; a device clock ahead of the server;
check-outs, refusals, conflicts and scans the ledger does not hold, left out; an erased member, left out; the verbs a
journal declares; the criteria, read by the `QuestReader` of `tilttek/quests` and counted on what the recorder
recorded; the passport, every day, distinct places and a series of weeks with gaps; and the summary, refused to anyone
but the member. In the monorepo, Infection holds the library at 100 % of covered mutants.

The integration test, under `tests/Integration`, registers a listener that calls the recorder on an event dispatcher
of Symfony, and dispatches the events of `phil/checkin-bundle`, built by its own named constructors, online and
synchronised from an offline journal, twice.

## Testing code that rewards attendance

Build the ledger of `phil/checkin` and the journal of `tilttek/activity` in memory, on a mock clock, append a check-in
to the ledger and hand its identifier to the recorder:

```php
$ledger = new InMemoryCheckInLedger();
$journal = new Journal(new InMemoryActivityLog(), new MockClock('2027-03-05 18:00:00 UTC'));
$recorder = new AttendanceRecorder($ledger, $presences, $journal);

$checkIn = CheckIn::restore(ScanId::of('scan-1'), 'ticket-44', CheckpointId::of('panel-7'), Direction::In,
    ScanOutcome::Admitted, DeviceId::of('desk-1'), $now, $now, 1);
$ledger->append($checkIn);
$recorder->record($checkIn->id);
```

Then read the journal's totals, or the steps of a quest built from `AttendanceCriterion`, as the application does.
