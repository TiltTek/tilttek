<!-- phil-dev source-sha256=33427fd515b04e4dae017dc1257448a19ff942afa8ad752f976d35b2de9fe043 -->
# Macros

Une entrée peut nommer des macros : `$attack + 2` prend l'arbre de la macro `attack` comme opérande. L'analyseur lit le
nom comme un seul jeton — un `$` et les lettres et chiffres écrits contre lui, mis en minuscules — et demande aux
`Macros` qu'on lui a donnés le lot de la macro. Il ne remplace jamais de texte : une macro `a` laisse intacts `aa`,
`$aa` et `2d6a10`, et une macro garde ses propres parenthèses : `$attack*2` vaut `(1d20+5)*2` quand `attack` vaut
`1d20+5`.

```php
use Tilttek\Dice\Contract\Macros;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Expression\Pool;

final class SheetMacros implements Macros
{
    public function __construct(private DiceRoller $dice) {}

    public function pool(string $name): ?Pool
    {
        return 'attack' === $name ? $this->dice->parse('1d20+5', macros: $this) : null;
    }
}

$dice = new DiceRoller();
$dice->write($dice->parse('$attack*2', macros: new SheetMacros($dice)));   // "(1d20+5)*2"
```

| Règle | Exemple |
|---|---|
| Un nom est une lettre, puis des lettres et des chiffres, 32 au plus | `$attack` ; `$Stealth2` vaut `stealth2` |
| Une macro d'une seule instruction est un opérande partout où un opérande peut se trouver | `1d20i[>10]{$attack}` |
| Une macro de plusieurs instructions est toute l'entrée, écrite seule | `$init` vaut `1d20, $1+2` |
| Un nom inconnu est refusé à son `$` | `No macro is named "sword" at position 2 of "1+$sword".` |
| Une macro qui ne s'analyse pas est refusée avec sa raison | `The macro "broken" does not parse: Unexpected "x"` |
| `$` suivi de chiffres reste une référence | `$1` |

Sans `Macros`, `$attack` est refusé comme il l'a toujours été. `Macros::pool()` résout les macros que nomme une macro,
et lève `InvalidExpression` pour une macro qui se nomme elle-même. Le lot que donne l'entrée est vérifié au regard des
[limites](limits.md) une fois ses macros intégrées. `tilttek/dice-bundle` garde des macros pour un site, une table et un
membre.
