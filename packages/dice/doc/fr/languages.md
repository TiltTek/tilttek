<!-- phil-dev source-sha256=df1989f65c9244fb72f62b7d541e4ef82557680877cd1820a6bc8e434866d8fc -->
# Langues

Une expression se lit et s'écrit dans la notation d'une langue : `2W6` en allemand pour *Würfel*, `2T6` en danois, en
norvégien ou en suédois pour *terning*.

Une `Notation` déclare la lettre d'un dé et les mots de l'avantage et du désavantage. La lecture accepte aussi les
lettres canoniques ; l'écriture utilise celles de la langue. Les autres modificateurs gardent leurs lettres canoniques
dans toutes les langues. La lettre d'un dé est une seule lettre autre que `f`, `h`, `k`, `l` ou `r`, qu'utilisent les
modificateurs ; les mots comptent de 2 à 16 lettres et diffèrent l'un de l'autre.

`Notations` trouve la notation d'une locale — `de_AT` et `de-CH` se rabattent sur `de` — et donne la notation canonique
pour une langue qui n'en déclare aucune. L'allemand, le danois, le norvégien (`nb`, `nn`, `no`) et le suédois sont
intégrés ; une application en ajoute ou en remplace :

```php
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Notation\Notation;
use Tilttek\Dice\Notation\Notations;

$dice = new DiceRoller(notations: new Notations(['xx' => new Notation('q', 'up', 'down')]));
$pool = $dice->parse('2W6 + W20 adv', 'de');
$dice->write($pool);        // "2d6+2d20kh1"
$dice->write($pool, 'de');  // "2W6+2W20kh1"
```
