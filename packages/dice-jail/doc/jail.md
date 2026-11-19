# The jail

`Jail\Warden` sends a member's die to jail and lets it out.

```php
$inmate = $warden->jail('member-7', 'd20:blue', 'Three natural ones in a row.');
$warden->jail('member-7', 'd6:red', until: new DateTimeImmutable('+1 day'));   // a sentence that ends by itself

$warden->inmate('member-7', 'd20:blue');       // the Inmate, or null when free
$warden->inmates('member-7');                  // the dice in jail now, in the order they went in
$warden->release('member-7', 'd20:blue', 'It has learned its lesson.');
$warden->history('member-7', 'd20:blue');      // every JailEvent, in order
```

## Sentences

A sentence without end lasts until the die is released. A sentence with an end lets the die out at that moment by
itself: from then on the die is free, cannot be released, and may go back to jail. A die is in jail once at a time.

## The keeper and the die

The keeper is the stable identifier of the member whose die it is — never an email address: one containing `@` is
refused. The die is the application's name for it: `d20:blue`, a die's id. Both are 1 to 128 bytes of UTF-8 without
control characters; a reason is up to 500 characters, line breaks allowed. A keeper holds at most 1 000 dice in jail
at once.

## Evidence

`Evidence::of($result)` keeps, with the jailing, the statistics the table was shown: the verdict, the number of rolls,
the p-value and the means. The jail never asks for it, and never reads it.

## What the jail is not

The jail is a gesture of play. It changes nothing in how a die rolls, takes nothing from a member, and needs no
statistics. It is private to the keeper unless the application shows it; nothing in it ranks members or dice.

## Concurrent writers

Each event carries its position in the die's history; `Contract\JailStore::append()` throws `Exception\JailConflict`
when another writer came first, and nothing is written.
