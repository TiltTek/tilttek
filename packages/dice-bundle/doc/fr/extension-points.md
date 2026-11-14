<!-- phil-dev source-sha256=a7d343a6de07c95df054b09e8e96b7c4be0ac7a1fc9b3966476ef78b3bf0f4c5 -->
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
        return new DieAppearance('Ember', '#aa0000', 'resin', '/images/ember.png', ink: '#ffd700', texture: 'dice/ember');
    }
}
```

Nommez le service dans `tilttek_dice.appearance`. **L'apparence ne change jamais le hasard** : elle habille les dés que
le serveur a lancés. Les dés Fudge demandent 6 faces ; les dés personnalisés portent leurs propres faces et n'en
demandent aucune.

| Argument | Contient | Vérifié |
|---|---|---|
| `name` | Le nom du jeu | Non vide |
| `color` | La couleur des dés | `#rrggbb` |
| `material` | Un mot pour la feuille de style : `resin`, `metal`, `glass`… | — |
| `image` | Une image du jeu | — |
| `ink` | La couleur des nombres | `#rrggbb` |
| `texture` | Une clé que l'application associe à sa propre image, jamais une adresse | De 1 à 128 caractères parmi `a-z`, `0-9`, `.`, `_`, `/` et `-`, commençant par une lettre ou un chiffre, sans `..`, `//` ni `/` final |

Une valeur qui échoue à sa vérification lève `Tilttek\Dice\Exception\InvalidDefinition` à la construction de
l'apparence. Passez `ink` et `texture` par leur nom.

**Les nombres se lisent toujours.** `readableInk()` donne l'encre donnée ou, sur un dé qui a une couleur et pas d'encre,
le noir ou le blanc, selon celui qui contraste le plus avec la couleur : toujours à 4,5:1 au moins, le contraste que
WCAG 2 demande pour du texte. Un dé sans couleur n'a pas d'encre calculée : la feuille de style lui en donne une.
`Application\Contrast` mesure le contraste de deux couleurs, pour une application qui vérifie les encres qu'elle
accepte. `toArray()` donne l'apparence comme le détail d'un lancer la montre, encre lisible comprise, prête pour un
message JSON.

## Les dialectes

Un service qui étend `Tilttek\Dice\Dialect\Dialect` reçoit l'étiquette `tilttek_dice.dialect` par la configuration
automatique et s'ajoute aux dialectes ; s'il porte le nom d'un dialecte intégré, il le remplace.

## Le journal

`Tilttek\DiceBundle\Domain\Repository\RecordedRollRepository` est le port du journal : un lancer trouvé par son
identifiant, ou plusieurs à la fois dans l'ordre demandé (au plus `RecordedRollRepository::MANY`, soit 500),
enregistré, listé par propriétaire ou par contexte, le plus récent d'abord, et effacé par propriétaire. Une application
peut lui donner pour alias sa propre implémentation ; celles du bundle sont sur Doctrine et en mémoire.

## Un lancer enregistré

`DiceRolls` émet `Tilttek\DiceBundle\Application\Event\RollRecorded` après avoir enregistré chaque lancer rejouable,
quand l'application a un répartiteur d'événements. L'événement ne porte que des identifiants, ceux du lancer, de son
propriétaire et de son contexte : un écouteur lit le lancer dans le journal, et un message qu'il transmet, à Mercure
ou à une file, porte les mêmes.

```php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tilttek\DiceBundle\Application\Event\RollRecorded;

#[AsEventListener]
final class AnnounceRoll
{
    public function __invoke(RollRecorded $event): void
    {
        // $event->id, $event->owner, $event->context
    }
}
```

## Les macros

`Tilttek\DiceBundle\Domain\Repository\DiceMacroRepository` est le port des [macros](macros.md) : une macro trouvée par
son niveau, son propriétaire et son nom, enregistrée, retirée, listée par propriétaire et effacée par propriétaire. Une
application peut lui donner pour alias sa propre implémentation ; celles du bundle sont sur Doctrine et en mémoire.
Une application qui garde ses macros tout à fait ailleurs implémente `Tilttek\Dice\Contract\Macros` et la passe à
`DiceRoller::parse()`.
