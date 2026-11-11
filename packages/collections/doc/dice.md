# Dice

The items of a set of dice carry a `CollectibleDie`: their number of sides, a colour and a material. Three kinds of
sets suit dice: a `base` set every member gets on arrival, an `earned` set obtained for the site's points, and a
`paid` set bought for oneself or as a gift.

`DiceShelf::dice(member)` lists the member's copies that are dice; `choose(member, copy)` makes one of them the die the
member rolls for its number of sides — one choice per number of sides —; `chosen(member, sides)` returns its item, or
null for the plain dice; `clear(member, sides)` goes back to them. A die traded away is no longer rolled: the choice
falls back to the plain dice until the member chooses another.

`tilttek/collections-bundle` turns the chosen die into the appearance `tilttek/dice-bundle` shows. The appearance never
changes the randomness.
