# Printed cards

A session's bingo is often played on paper: the organiser prints a batch of cards, hands them out, calls the squares,
and checks the card of whoever shouts. Printed cards belong to a game whose squares are called: in a game ticked by
activity, each card belongs to a member, and none is printed.

## Printing a batch

```php
use Tilttek\Bingo\View\PrintedCardView;

$cards = $bingo->print('night-12', 120);                       // list<Card>, held by no player
$views = PrintedCardView::ofBatch($bingo, $bingo->game('night-12'), $cards);
```

`Bingo::print($game, $count)` deals 1 to 1 000 cards at once: their serials are reserved in one change of the game, and
each card is drawn from the server's secret like any other, unlike every card of the game; a serial whose layout is
taken is passed over for a new one, up to 32 times in the batch. A printed card has no player: `isPrinted()` says so,
no gesture ticks it, and a player's claim on it is refused. The batch is kept in the store, so that each card is checked
later by its serial.

## The label

Each printed card shows a label: its serial and a check code of eight characters, written `42-7KQ3-M9XA`.
`Bingo::label($game, $serial)` gives it as a `SerialLabel`; `toString()` writes it. The code is the start of an
HMAC-SHA256 of the game and the serial under the server's secret, under a key of its own, so that it tells nothing of
the layout; it is written in Crockford's base 32 — digits and capital letters without I, L, O and U, which read alike.
Without the secret, nobody can write the code of a serial: a card drawn by hand with a made-up number, or a photocopy
given another number, is told apart at once.

## Checking a printed card

```php
$claim = $bingo->verifyLabel('night-12', $typed);              // what the organiser typed
$claim->isWon();
```

`Bingo::verifyLabel()` reads the label as the organiser types it — any case, spaces or hyphens between its parts, O read
as 0, I and L as 1, at most 32 bytes —, refuses a code that is not the one the secret draws for the serial, then does
what `Bingo::verify()` does: it draws the layout again from the seed, refuses a card whose stored layout differs, and
wins the card when the squares called complete a pattern. `SerialLabel::parse()` reads a label the same way for a form;
anything else is refused with `InvalidBingo`. A card wins once: `BingoWon` names it with a null player.

## The template

`templates/print.html.twig` prints a batch from a list of `View\PrintedCardView`: each card is a section of its own,
with its grid and its label. Register the folder under a namespace of the application, as for the card template; see
[Celebrations](celebrations.md#the-card-template).

```twig
{% embed '@TilttekBingo/print.html.twig' with {cards: views, labels: {caption: 'bingo.card'|trans, free: 'bingo.free'|trans, check: 'bingo.check'|trans}} %}
    {% block footer %}<p>{{ 'bingo.rules'|trans }}</p>{% endblock %}
{% endembed %}
```

The variable `labels` gives the words of the page: `caption`, `free` and `check`, in English by default. The block
`footer`, empty by default, is drawn under each card: the rules, the prize. The application's style sheet puts each card
on a page of its own:

```css
@media print {
    .isBingoPrintedCard { break-after: page; break-inside: avoid; }
}
```

The page is HTML for the browser's print dialog; an application that wants a PDF of the batch renders the same page with
its own HTML-to-PDF tool.
