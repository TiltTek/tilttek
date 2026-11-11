<!-- phil-dev source-sha256=392e68140ec461e4e73513768ee55c380d487c4d88ff9eb08696f885b074df2b -->
# Points d'extension

## Les dés d'un membre

`Tilttek\DiceBundle\Application\Port\DiceAppearance` donne, pour un membre et un nombre de faces, l'apparence des dés à
afficher, par exemple le jeu que possède le membre, ou null pour des dés ordinaires :

```php
use Phil\Resource\ResourceReference;
use Tilttek\DiceBundle\Application\DieAppearance;
use Tilttek\DiceBundle\Application\Port\DiceAppearance;

final class OwnedDice implements DiceAppearance
{
    public function appearance(ResourceReference $member, int $sides): ?DieAppearance
    {
        return new DieAppearance('Ember', '#aa0000', 'resin', '/images/ember.png');
    }
}
```

Nommez le service dans `tilttek_dice.appearance`. Une `DieAppearance` a un nom, une couleur écrite `#rrggbb`, un
matériau et une image. **L'apparence ne change jamais le hasard** : elle habille les dés que le serveur a lancés. Les
dés Fudge demandent 6 faces ; les dés personnalisés portent leurs propres faces et n'en demandent aucune.

## Les dialectes

Un service qui étend `Tilttek\Dice\Dialect\Dialect` reçoit l'étiquette `tilttek_dice.dialect` par la configuration
automatique et s'ajoute aux dialectes ; s'il porte le nom d'un dialecte intégré, il le remplace.

## Le journal

`Tilttek\DiceBundle\Domain\Repository\RecordedRollRepository` est le port du journal. Une application peut lui donner
pour alias sa propre implémentation ; celles du bundle sont sur Doctrine et en mémoire.

## Les macros

`Tilttek\DiceBundle\Domain\Repository\DiceMacroRepository` est le port des [macros](macros.md) : une macro trouvée par
son niveau, son propriétaire et son nom, enregistrée, retirée, listée par propriétaire et effacée par propriétaire. Une
application peut lui donner pour alias sa propre implémentation ; celles du bundle sont sur Doctrine et en mémoire.
Une application qui garde ses macros tout à fait ailleurs implémente `Tilttek\Dice\Contract\Macros` et la passe à
`DiceRoller::parse()`.
