# Cards and their seed

## How a card is drawn

`CardDealer` holds the server's secret, at least 32 bytes. The seed of card `n` of a game is

```text
HMAC-SHA256(secret, "tilttek-bingo" NUL game NUL n)
```

and it seeds PHP's `Xoshiro256StarStar` engine, from which a `Random\Randomizer` picks as many squares as the card holds
and shuffles them; the free centre is put back in the middle. A seeded engine and these methods give the same results
on every PHP version, so the same secret, game and serial always draw the same layout.

No seed comes from the browser: without the secret, nobody can tell another player's card nor choose their own, and
the server needs nothing but the serial to draw a card again.

## Unique cards

The fingerprint of a layout is the SHA-256 of its squares in order. The store refuses a card whose fingerprint another
card of the game holds; `Bingo::deal()` then passes the serial over and draws the next, up to 32 times. A pool barely
larger than a card leaves few layouts: a 3 × 3 card with a free centre drawn from 8 squares has 40 320.

## Checking a card

A stored card keeps its layout, for the page that shows it. A claim and a check draw the layout again from the seed and
refuse a card whose stored layout differs: a card changed in the store, or forged, never wins. A printed card shows its
label, its serial and a check code the secret draws; the organiser checks it with `Bingo::verifyLabel()`, which refuses
a code that is not the serial's before the check of `Bingo::verify($game, $serial)`; see [Printed cards](printing.md).

Changing the secret changes every card drawn after, and every check code: keep it for the life of the games whose
cards are checked.
