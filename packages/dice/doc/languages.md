# Languages

An expression is read and written in the notation of a language: `2W6` in German for *Würfel*, `2T6` in Danish,
Norwegian or Swedish for *terning*.

A `Notation` declares the letter of a die and the words of advantage and disadvantage. Reading accepts the canonical
letters as well; writing uses the language's. The other modifiers keep their canonical letters in every language. The
letter of a die is one letter other than `f`, `h`, `k`, `l` or `r`, which the modifiers use; the words are 2 to 16
letters, different from each other.

`Notations` finds the notation of a locale — `de_AT` and `de-CH` fall back on `de` — and gives the canonical one for a
language that declares none. German, Danish, Norwegian (`nb`, `nn`, `no`) and Swedish are built in; an application adds
or replaces any:

```php
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Notation\Notation;
use Tilttek\Dice\Notation\Notations;

$dice = new DiceRoller(notations: new Notations(['xx' => new Notation('q', 'up', 'down')]));
$pool = $dice->parse('2W6 + W20 adv', 'de');
$dice->write($pool);        // "2d6+2d20kh1"
$dice->write($pool, 'de');  // "2W6+2W20kh1"
```
