<!-- phil-dev source-sha256=36d8ffd3bc6c5d08da709f6ce083b458d4397977cf5fde213c1f0067d5371604 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Garder les étagères | Implémentez `Contract\ShelfStore`, et passez-le à `GameShelves`. |
| Agir sur ce qui s'est passé | Implémentez `Contract\ShelfObserver`, et passez-le à `GameShelves`. |
| Donner à une soirée de jeux les jeux que les membres peuvent apporter | Adaptez `GameShelves::bringable()` au port de la soirée de jeux. |

## Le stockage

`Contract\ShelfStore` garde une étagère par membre, à une version : `shelf()` la lit, et donne une étagère vide à la
version 0 quand aucune n'est gardée ; `save()` la garde à la version suivante et la retourne. Une étagère à la version 0
est nouvelle, et le stockage n'a aucune étagère pour son membre ; toute autre n'est gardée que si le stockage la détient
à cette version, et `ShelfConflict` est levée sinon, sans que rien soit gardé. Une étagère contient ses exemplaires, ses
souhaits dans leur ordre, ses notes et sa visibilité : une seule écriture les garde ensemble.

`Store\InMemoryShelfStore` garde les étagères en mémoire, aussi longtemps que l'objet dure : il sert aux tests. Une
application les garde dans sa base de données ; chaque valeur ne contient que des chaînes, des nombres, des booléens et
des énumérations, et l'ordre des souhaits est celui de `Shelf::wishes()`. Un stockage garde la marque `imported` de chaque
exemplaire : un exemplaire relu sans elle est un exemplaire saisi à la main, qu'une importation synchronisée ne touche
jamais.

## L'observateur et ses événements

`Contract\ShelfObserver::notify()` apprend chaque fait une fois l'étagère gardée, par identifiants seulement :

| Événement | Quand | Porte |
|---|---|---|
| `Event\CopyAdded` | Un exemplaire a été placé, ou a reçu un autre jeu. | l'exemplaire |
| `Event\CopyRemoved` | Un exemplaire a été retiré, ou a reçu un autre jeu. | l'exemplaire |
| `Event\CopyOfferedForTrade` | Un exemplaire est devenu à échanger. | l'exemplaire |
| `Event\CopyWithdrawnFromTrade` | Un exemplaire à échanger ne l'est plus, ou a quitté l'étagère. | l'exemplaire |
| `Event\GameWished` | Un jeu a rejoint la liste de souhaits. | — |
| `Event\WishWithdrawn` | Un jeu a quitté la liste de souhaits. | — |

Chaque événement implémente `Event\ShelfEvent`, dont `member` et `game` sont des identifiants. Une application en tire
un message — par Messenger, avec les identifiants seuls — et lit le reste dans l'étagère.

Les faits sont annoncés une fois que le magasin a conservé l'étagère. Quand l'observateur échoue sur un fait, il est
tout de même informé des autres, puis la première exception qu'il a levée est levée de nouveau par la méthode qui a
fait le changement ; l'étagère reste conservée. Une application qui ne doit jamais perdre un fait garde l'étagère et
les messages de son observateur dans une seule transaction — un transport Messenger sur la même base de données, par
exemple — afin qu'un fait en échec annule le changement. Un événement est annoncé
quelle que soit la visibilité de l'étagère : un service d'échange ou un rappel de stock n'agit sur lui que dans la
mesure où le membre montre l'étagère ou a accepté d'être informé, et ne rend jamais publique une liste de souhaits
privée.

## Les étagères d'une soirée de jeux

`GameShelves::bringable()` donne les jeux que les membres peuvent apporter à une soirée de jeux sous forme de
`BringableGame`, avec le jeu et son propriétaire, une fois par membre et par jeu, jusqu'à une limite. Une bibliothèque
de soirée de jeux qui lit les jeux des membres par un port à elle les reçoit par un adaptateur que l'application écrit ;
le paquet ne dépend d'aucune bibliothèque de ce genre.

Pour `tilttek/game-night`, dont le `Contract\ShelfReader` donne des valeurs `Choice\OwnedGame` :

```php
use Override;
use Tilttek\GameNight\Choice\OwnedGame;
use Tilttek\GameNight\Contract\ShelfReader;
use Tilttek\GameShelf\GameShelves;

final readonly class MemberShelves implements ShelfReader
{
    public function __construct(private GameShelves $shelves)
    {
    }

    #[Override]
    public function games(array $members, int $limit): iterable
    {
        foreach ($this->shelves->bringable($members, $limit) as $game) {
            yield new OwnedGame($game->game, $game->owner);
        }
    }
}
```

Les deux bibliothèques prennent les identifiants du catalogue pour les jeux, et des membres sans « @ » : les valeurs
passent telles quelles. `bringable()` lit au plus 2000 membres et donne au plus 10 000 jeux à la fois, plus que ce
qu'une soirée de jeux demande. Un exemplaire qui n'est pas possédé, ou que son membre garde à la maison avec
`bringable: false`, n'est jamais donné.

## Sûr avec les workers

Le service et tout ce qu'il utilise ne gardent rien entre les appels : ils peuvent vivre aussi longtemps qu'un worker,
et servir plusieurs sites d'une même application à la fois, le stockage décidant des étagères qu'il lit.
