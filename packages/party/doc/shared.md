# What the party shares

## The purse

The purse holds whole coins by currency: counts of the game's smallest coin under a key the application chooses —
`gp`, `sp`, `credits` —, never real money. A currency key follows the rule of keys and starts with a letter.

```php
$parties->deposit($party, 'gp', 250, $by);   // the purse after the deposit
$parties->withdraw($party, 'gp', 40, $by);   // never more than it holds
$parties->purse($party)->amount('gp');       // 210
```

An amount is 1 to 1,000,000,000,000 coins, and a currency holds at most as many; a purse holds at most 20 currencies.
A withdrawal beyond the balance is refused with `Refusal::NotEnoughFunds`. A currency that reaches zero is dropped;
`amounts()` reads the others in the order they were first deposited.

`Purse` is a value: `deposit()` and `withdraw()` give a new purse, so an application can compute with one before it
writes anything.

## Shares

`shares()` divides a currency evenly, in whole coins, without taking anything out; `distribute()` takes the shares out
of the purse and returns the same `Split`:

```php
$split = $parties->distribute($party, 'gp');   // among the active members
$split->share;                                 // the coins each receives
$split->remainder;                             // fewer coins than members, kept in the purse
$split->shares();                              // ['character:12' => 70, 'character:13' => 70, ...]

$parties->shares($party, 'gp', [$ada, $bo]);   // among chosen members, active or away, each once
```

By default the active members receive a share, in the order of the party; a list given instead names members of the
party, active or away, each counted once in the order given (`Refusal::NotMember` for anyone else). With no one to
receive a share, `Refusal::NoOneToShare`. A share smaller than a coin leaves the whole amount in the purse.

The library keeps only the party's purse: the application credits each member with its share where it keeps the
characters' own coins.

## Loot

Loot entries live under keys the application gives, each with a name of 1 to 100 characters and a quantity of 1 to
1,000,000. An entry is carried by a member or lies in the stash:

```php
$parties->addLoot($party, 'loot.rope', 'Silk rope', 2);          // in the stash
$parties->giveLoot($party, 'loot.rope', $ada);                   // Ada carries it
$parties->giveLoot($party, 'loot.rope', null);                   // back in the stash
$parties->removeLoot($party, 'loot.rope', 1);                    // one used; null when none is left
```

A key holds one entry (`Refusal::LootExists`); a party holds at most `Limits::$lootPerParty` entries
(`Refusal::TooMuchLoot`); a holder belongs to the party (`Refusal::NotMember`); removing more than the quantity is
refused with `Refusal::NotEnoughLoot`. `loot()` reads the entries, the earliest added first, ties by key. Loot a member
carried goes back to the stash when it leaves or is erased.

## Notes

A member writes a note under a key, 1 to 5000 characters of UTF-8, not blank, with line breaks and tabs the only
control characters:

```php
$parties->writeNote($party, 'lead.mill', $ada, "The miller lies.\nAsk his daughter.");
$parties->editNote($party, 'lead.mill', 'The miller is innocent.', $by);
$parties->removeNote($party, 'lead.mill', $by);
```

The author belongs to the party (`Refusal::NotMember`); a key holds one note (`Refusal::NoteExists`); a party holds at
most `Limits::$notesPerParty` notes (`Refusal::TooManyNotes`). An edit keeps the author and the moment it was written,
and records when it was edited. `notes()` reads the notes, the earliest written first, ties by key. A note stays when
its author leaves, and loses its author when that member is erased.
