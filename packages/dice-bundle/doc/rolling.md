# Rolling

`DiceRolls` is the service the application calls. An expression is a `Pool` or a string, read in the notation of the
locale given — the canonical one when it is null —; `readRolisteam()` reads a command of Rolisteam's DiceParser, and
`DiceMacros` an input that names [macros](macros.md).

```php
$dice->parse('2W6', 'de');                      // a Pool
$dice->readRolisteam('5D10K3');                 // a Pool, from Rolisteam's DiceParser
$dice->write($pool, 'de');                      // "2W6"
$dice->translate($pool, 'foundry')->text();     // "/r 2d6"
$dice->roll('4d6kh3')->totals();                // [13], for instance
```

Plain rolls come from PHP's secure engine. Each roll takes the rounding of its divisions, down by default; a game that
rounds up for one side and down for the other passes the mode of the one who rolls:

```php
$dice->roll('1d6/3', null, RoundingMode::PositiveInfinity)->totals();             // [2], for instance
$dice->translate($pool, 'roll20', RoundingMode::PositiveInfinity)->notices;       // the divisions a table rounds down
```

## Replayable rolls

```php
$recorded = $dice->rollReplayable('4d6kh3', $member);   // kept in the journal
$replay = $dice->replay($recorded->id());
$replay->result;      // the roll, die for die
$replay->matches();   // whether it gave what the journal holds
```

`rollReplayable()` draws an identifier and a seed, rolls with the rounding given, and saves a `RecordedRoll` dated by
the clock, its rounding included; the owner — a `ResourceReference` — is optional. `replay()` rolls it again from its
expression, its seed and its rounding, and raises `UnknownRoll`
for an identifier the journal does not hold. `rollsOf($member, $limit)` lists a member's latest rolls, the latest first.

## Verifiable rolls

```php
$seed = $dice->newSeed();
$commitment = $seed->commitment()->hex;          // publish it, keep the seed secret
$roll = $dice->rollVerifiable('1d20', $seed, $clientSeed, $nonce);
// … once the rolls of this seed are done, publish $roll->toArray(), which reveals the seed
$dice->verify($commitment, $seed, $clientSeed, $nonce, '1d20', $claimed);
```

A verifiable roll made with another rounding is verified with the same one, which `toArray()` publishes: pass it to
`rollVerifiable()` and to `verify()`. Every construct of the notation — instructions, branches, text lists, sentences
— is replayed and verified alike.

Where the seed is kept between the commitment and the reveal is the application's choice: a session, a table's row.

## Erasing a member's rolls

`eraseOwner($member)` erases every roll of a member from the journal and returns how many; call it when the person is
erased.
