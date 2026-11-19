# Running a raffle

## Stakes and sources

| Stake | Seeded by | Why |
|---|---|---|
| `Stake::Prize`: something of value is won | `SeedSource::Beacon`, always | An organiser who held the seed could draw again until a friend wins, before committing to it |
| `Stake::None`: who speaks first, who brings the snacks | `SeedSource::CommitReveal` by default, `SeedSource::Beacon` on request | Nothing to gain by cheating; no network needed |

`open()` refuses a prize seeded by its organiser, and a beacon raffle when no beacon is configured.

```php
$raffles->open('door-prize', Stake::Prize, winners: 3, alternates: 2);
$raffles->open('first-speaker', Stake::None);                          // commit-reveal
$raffles->open('pizza-topping', Stake::None, source: SeedSource::Beacon);
```

A raffle draws 1 to 1 000 winners and 0 to 1 000 alternates. A raffle seeded by commit-reveal draws its seed — 32
bytes from the secure randomizer — when it opens, and its commitment, the SHA-256 of the seed's 64 hexadecimal digits,
is in the raffle and in the announcement from then on.

## Entries: one chance per entrant

```php
$raffles->enter('door-prize', 'member:1042');
```

An entrant is an opaque reference of 1 to 128 letters, digits, dots, colons, underscores, tildes or hyphens: a member
or a ticket identifier, published in the proof, so never an address nor a name — the `@` of an address is refused. An
entrant enters once: a second entry is refused, nothing in the API takes a number of chances, and the draw is uniform
among the distinct entrants. A raffle takes up to 100 000 entrants, and no entry once it is sealed.

## Sealing

```php
$announcement = $raffles->seal('door-prize');
$announcement->toArray();     // raffle, stake, source, winners, alternates, entrants, entrants_hash, sealed_at,
                              // commitment, chain, round, drawable_at
```

`seal()` refuses a raffle without entrants, lists the entrants in byte order, hashes them, and, for a beacon, reads the
chain and fixes the first round published strictly after the closing plus the margin — 30 seconds by default, room for
the clocks of the server and of the beacon to differ. Publish the announcement at once, where the entrants see it: the
hash proves no entrant was added or removed afterwards; the round, announced before it exists, proves the organiser
could not choose it. `announcement()` gives it again later.

## Drawing

```php
$proof = $raffles->draw('door-prize');
$proof->winnerList();         // the winners, in the order drawn
$proof->alternateList();      // the alternates, in the order they replace a winner
```

A beacon raffle is drawn once its round is published: before `drawableAt()`, `draw()` refuses and fetches nothing.
The round fetched must be the round announced. A commit-reveal raffle is drawn at once, its seed revealed in the proof.
When there are fewer entrants than winners and alternates, every entrant is drawn, in a random order.

A raffle is drawn once: a winner who does not claim the prize is replaced by the next alternate, never by a new draw,
so that the order of replacement is fixed by the same seed and in the proof. `proof()` gives the proof again later.

## The event

Once the raffle is kept as drawn, `Raffles` dispatches `Event\RaffleDrawn`, which carries the raffle's identifier only.
A listener reads the proof to announce the winners, push them to a screen, or play a celebration — confetti on the
stream overlay —; the library itself shows nothing.
