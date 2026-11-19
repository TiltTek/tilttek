<!-- phil-dev source-sha256=e2e6e8ee21189cc9f183c41a69040ec6c3288d8b629936bdf49f7185e09ae4cb -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

## Qui peut regarder une table

Implémentez `Tilttek\DiceTower\Application\Contract\TableAccess` et nommez le service dans `table_access`. Il répond
pour le spectateur courant, à partir des membres et des tables de l'application :

```php
use Phil\Resource\ResourceReference;
use Symfony\Bundle\SecurityBundle\Security;
use Tilttek\DiceTower\Application\Contract\TableAccess;

final readonly class TableMembers implements TableAccess
{
    public function __construct(private Security $security, private Seats $seats)
    {
    }

    public function canWatch(ResourceReference $table): bool
    {
        $user = $this->security->getUser();

        return null !== $user && $this->seats->isSeated($user->getUserIdentifier(), $table->id);
    }
}
```

La surcouche, l'abonnement et la route des entrées manquées le consultent tous ; un spectateur qu'il refuse ne voit
rien et ne lit rien.

## Relais vers d'autres canaux

Implémentez `Tilttek\DiceTower\Application\Contract\TowerRelay` : chaque service qui l'implémente reçoit l'étiquette
`tilttek_dice_tower.relay` et reçoit chaque nouvelle entrée. Un relais vers un canal de clavardage — le canal Discord
de la table, par exemple — est un adaptateur qui est un paquet à part, lequel apporte le client de ce service ; ce
paquet-ci n'en a besoin d'aucun.

```php
use Tilttek\DiceTower\Application\Contract\TowerRelay;
use Tilttek\DiceTower\Application\TowerEntry;

final readonly class ChannelRelay implements TowerRelay
{
    public function relay(TowerEntry $entry): void
    {
        // Find the channel of $entry->table, and post the totals of $entry->results.
    }
}
```

Avec `message_bus`, les relais sont joints par ce bus : le message, `RelayDrop`, ne porte que la table et la position,
et son *handler* lit l'entrée dans le journal. Routez-le vers un transport asynchrone pour qu'un canal lent ne retarde
jamais un jet. Un relais qui échoue est journalisé et les autres reçoivent quand même l'entrée.

## Éditeurs

`TowerPublisher` pousse chaque nouvelle entrée aux spectateurs. L'éditeur du bundle pousse vers un hub Mercure ; faites
de l'interface un alias d'un service à vous pour pousser ailleurs :

```yaml
services:
    Tilttek\DiceTower\Application\Contract\TowerPublisher: '@App\Dice\SocketPublisher'
```

## Gabarits

Remplacez les gabarits comme pour tout bundle, sous `templates/bundles/TilttekDiceTowerBundle/` :

| Gabarit | Ce qu'il dessine |
|---|---|
| `overlay.html.twig` | La carte, sa liste, la tour vide et la ligne d'état, avec les valeurs du contrôleur |
| `_entry.html.twig` | Une entrée |

Un remplacement garde les mots de chaque dé et les valeurs du contrôleur. Il nomme les composants du kit par
`dice_tower_kit()`, afin de suivre le sous-répertoire de l'application. Le contrôleur écrit une nouvelle entrée comme
le fait `_entry.html.twig` : le remplacement de l'entrée change aussi le `item()` du contrôleur.

## Traductions

Les mots sont dans le domaine `tilttek_dice_tower`, en anglais et en français ; ajoutez un fichier
`translations/tilttek_dice_tower.<locale>.yaml` pour une autre langue, avec les clés des fichiers du bundle. Les
libellés des faces et les textes des jets passent par le traducteur de `TilttekDiceBundle`.
