# Extension points

## Events of the application

An event is any class implementing `Tilttek\DiceProbability\Event\Event`: it says whether the faces of a set of dice
match. It is computed exactly, like the built-in events, over the sorted multisets of the dice.

```php
use Tilttek\DiceProbability\Event\Event;
use Tilttek\DiceProbability\Event\Faces;

/** A full house: three of one value and two of another. */
final readonly class FullHouse implements Event
{
    public function matches(Faces $faces): bool
    {
        $counts = array_values($faces->counts);
        sort($counts);

        return [2, 3] === $counts;
    }
}

$probabilities->chance(new FullHouse(), $dice->parse('5d6'));   // 300/7776
```

## Dice of the application

A die of the application implementing `Tilttek\Dice\Die\NumericDie` rolls through `tilttek/dice`, but its faces cannot
be listed: its distributions are estimated from its rolls.
