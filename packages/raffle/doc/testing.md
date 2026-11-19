# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network or a database: raffles with and without a stake from
opening to proof, every refusal, one chance per entrant, the beacon round fixed after the margin and awaited, entrants
changed after the closing, raffles and proofs written and read back with everything else refused, every fault a
verifier finds, the shuffle against draws computed by a separate implementation in Python, and the drand adapter
against relays mocked with Symfony's `MockHttpClient` — agreeing, disagreeing, late, silent, too long, broken or lying
—, each one's outcome reported, the whole read ended on the clock, and a podium with alternates whose places are part
of the proof.
In the monorepo, Infection holds the library at 100 % of covered mutants.

`tests/Fuzz/proof.php` is the fuzz target of a published proof: any text gives a list of faults, never an exception.
`tests/Fuzz/drand.php` is that of the answers of a relay: any text is refused with `BeaconRefused` or read as a chain
or a round. `composer fuzz` runs both.

`tests/Benchmark/RaffleBench.php` measures one entry, the draw of 20 names among 100 000 entrants, and the proof of a
raffle of 10 000 entrants read and checked whole.

## Testing an application

Give `Raffles` a clock you move and a seeded randomizer, and a `RandomnessBeacon` of your own that returns rounds you
build: a `Beacon\Beacon` takes any signature whose SHA-256 is the randomness you give.

```php
$raffles = new Raffles(new InMemoryRaffleStore(), $fakeBeacon, clock: $clock, secure: new Randomizer(new Xoshiro256StarStar(7)));
```

Then check the proof the application publishes: `self::assertSame([], new RaffleVerifier()->problems($proof));`.
