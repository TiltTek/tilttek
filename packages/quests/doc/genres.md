# Genres

A quest's `QuestKind` says whose activity advances it and who accomplishes it.

## Personal

The member's own activity, for themselves. The member is the subject; the rewards go to them.

## Community

A challenge of the whole community: a collective goal — *a thousand games this month* — that the activity of every
member advances. It is accomplished once per occurrence, by the subject `community:<quest>`, and its rewards go to
**every member whose activity counted** towards it. With `OnAcceptance`, only the members who accepted it count.

## Team

Members join it under a team the application names — `team:red`, `guild:44` —, and their activity adds up towards a
goal they share. The team is the subject; the rewards go to **the members of the team who still follow it** when it is
accomplished. A member is in one team per quest at a time, and their activity counts from their own acceptance until
their own abandonment.

## Duel

A challenge one member sends another on a duel quest, under a key the application gives:

```php
$duels->challenge('duel:812', 'ten-games', $member, $friend);
$duels->accept('duel:812', $friend);    // or decline(); the challenger may withdraw() until then
$duels->standing('duel:812');            // how far each side is
$duels->expire();                        // the offers and the duels whose time is up
```

- An offer waits for its answer for a lifetime — `P7D` by default —; accepted late, it expires and the acceptance is
  refused.
- Once accepted, the duel lasts the quest's `duelLength` — `P7D`, `PT2H` — or until the quest closes, whichever comes
  first; without either, it lasts until it is won.
- Each side's activity counts from the acceptance to the end; **the first side to accomplish the quest wins**, which the
  projection decides: the duel is `Won`, its winner gets a completion under the duel's key and the rewards.
- A duel whose time runs out with no winner expires when `expire()` sweeps the duels; the observer is told.

A member may have several duels at once, on the same quest or not; each counts only its own time.
