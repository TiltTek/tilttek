<!-- phil-dev source-sha256=f4d77d1f5c301c6d4f82a615424373bb92af8195f685cfd3012d58bb347ed45d -->
# Points d'extension

## Événements de l'application

Un événement est toute classe qui implémente `Tilttek\DiceProbability\Event\Event` : elle dit si les faces d'un ensemble
de dés correspondent. Il est calculé exactement, comme les événements intégrés, sur les multiensembles triés des dés.

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

## Dés de l'application

Un dé de l'application qui implémente `Tilttek\Dice\Die\NumericDie` est lancé par `tilttek/dice`, mais ses faces ne
peuvent pas être énumérées : ses distributions sont estimées à partir de ses jets.
