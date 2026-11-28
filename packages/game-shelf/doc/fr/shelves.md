<!-- phil-dev source-sha256=5d70c44558e484427badb98146ba0cc518617ce6ec0c1dc838becdfae482b3b2 -->
# Étagères

Un membre a une étagère, lue par `GameShelves::shelf()` : un membre qui n'a jamais rien gardé a une étagère vide à la
version 0. Un membre est un identifiant stable et opaque, jamais un nom ni une adresse : il ne contient aucun « @ ».
Les jeux sont les identifiants des fiches de `tilttek/game-catalog`.

Chaque changement passe par `GameShelves`, qui lit l'étagère, la change, la garde à la version suivante, puis dit à
l'observateur ce qui s'est passé.

## Exemplaires

Un `Copy` est une boîte : son propre identifiant, son jeu, ses statuts, l'édition que le membre nomme, l'endroit où elle
est rangée et si elle peut quitter la maison.

| Statut | Sens |
|---|---|
| `CopyStatus::Owned` | Le membre la possède ; la valeur par défaut. |
| `CopyStatus::PreviouslyOwned` | Le membre l'a possédée autrefois. |
| `CopyStatus::Preordered` | Le membre l'a commandée et l'attend. |
| `CopyStatus::ForTrade` | Le membre l'offre en échange. |

Un exemplaire a au moins un statut, chacun une seule fois. Possédé et possédé auparavant ne vont pas ensemble, et un
exemplaire à échanger est possédé. Les statuts sont gardés dans l'ordre du tableau ci-dessus.

```php
$shelves->place('member-1', new Copy('copy-1', 'harbour', edition: 'French', location: 'Living room'));
$shelves->place('member-1', new Copy('copy-2', 'harbour', [CopyStatus::Owned, CopyStatus::ForTrade], 'English'));
$shelves->remove('member-1', 'copy-2');
```

`place()` ajoute un exemplaire, ou remplace l'exemplaire de même identifiant. Un nouvel exemplaire, ou un exemplaire à
qui l'on donne un autre jeu, est d'un jeu que le catalogue contient et ne cache pas : `EntryNotFound` ou `ShelfRefused`
sinon. Un exemplaire gardé d'un jeu que le catalogue cache depuis peut encore changer. L'édition et l'emplacement font
de 1 à 100 caractères sur une seule ligne.

Un exemplaire peut être apporté à une soirée de jeux quand il est possédé et que `bringable` est vrai ;
`bringable: false` garde à la maison une boîte fragile ou prêtée.

Un exemplaire qu'une importation a placé est `imported` ; un exemplaire que le membre place ne l'est pas. Un exemplaire
placé par-dessus un exemplaire importé du même jeu reste importé, pour que le membre puisse nommer son édition ou son
emplacement ; s'il reçoit un autre jeu, c'est un exemplaire saisi à la main. Seule une importation synchronisée lit la
marque : voir [Importation et exportation](import-export.md).

## Souhaits

Un `Wish` est un jeu que le membre souhaite, avec ce qu'il en souhaite et à quel point.

| Genre | Sens |
|---|---|
| `WishKind::Wishlist` | Sur la liste de souhaits ; la valeur par défaut. |
| `WishKind::WantToBuy` | Le membre veut l'acheter. |
| `WishKind::WantInTrade` | Le membre le veut dans un échange. |
| `WishKind::WantToPlay` | Le membre veut y jouer. |

| Niveau | Priorité des fichiers de collection |
|---|---|
| `WishLevel::MustHave` | 1 |
| `WishLevel::LoveToHave` | 2 |
| `WishLevel::LikeToHave` | 3 |
| `WishLevel::ThinkingAboutIt` | 4 |
| `WishLevel::DontBuy` | 5 |

Un membre souhaite un jeu une seule fois. Un nouveau souhait va en dernier dans l'ordre du membre ; un souhait remplacé
garde sa place. `moveWishBefore()` et `moveWishAfter()` déplacent un souhait à la place avant ou après un autre :
l'ordre est fixé par le membre, jamais par des numéros.

```php
$shelves->wish('member-1', new Wish('lanterns', [WishKind::Wishlist, WishKind::WantToBuy], WishLevel::MustHave));
$shelves->wish('member-1', new Wish('tide'));
$shelves->moveWishBefore('member-1', 'tide', 'lanterns');
$shelves->unwish('member-1', 'lanterns');
```

`Shelf::wishes()` donne les souhaits dans l'ordre du membre. `Shelf::wishlist()` donne la liste de souhaits : les
souhaits de « à posséder absolument » à « ne pas acheter », puis ceux sans niveau, dans l'ordre du membre à
l'intérieur de chaque niveau.

## Notes

Une `Note` est ce que le membre garde d'un jeu pour lui, à part de la fiche du catalogue : une cote de 1 à 10, un
commentaire d'au plus 2000 caractères avec des sauts de ligne, jusqu'à 20 étiquettes de 1 à 40 caractères sans « ; »,
et le nombre de parties jouées, de 0 à 1 000 000. Une note porte sur un jeu que le catalogue contient, caché ou non ;
une note vide supprime la note de son jeu.

```php
$shelves->note('member-1', new Note('harbour', rating: 8, comment: 'Best with four.', tags: ['family'], plays: 12));
$shelves->note('member-1', new Note('harbour'));    // drops it
```

## Bornes

| Borne | Valeur |
|---|---|
| `Shelf::MAX_COPIES` | 5000 exemplaires |
| `Shelf::MAX_WISHES` | 1000 souhaits |
| `Shelf::MAX_NOTES` | 5000 notes |
| `Copy::MAX_EDITION`, `Copy::MAX_LOCATION` | 100 caractères |
| `Note::MAX_COMMENT` | 2000 caractères |
| `Note::MAX_TAGS`, `Note::MAX_TAG` | 20 étiquettes de 40 caractères |
| `Note::MAX_PLAYS` | 1 000 000 de parties |

Une étagère pleine n'accepte rien de nouveau, mais remplace encore ce qu'elle contient. Chaque texte est de l'UTF-8
valide, sans espaces aux extrémités, et ne contient aucun caractère de contrôle — hors les sauts de ligne d'un
commentaire — ni aucun des caractères qui inversent le sens d'un texte.

## Visibilité et vues

`share()` montre l'étagère et la liste de souhaits, chacune à personne (`Visibility::Private`, la valeur par défaut),
aux membres connectés (`Visibility::Members`) ou à tout le monde (`Visibility::Public`).

`view()` donne un `ShelfView` pour un observateur — `Viewer::Owner`, `Viewer::Member` ou `Viewer::Anyone`, selon ce que
l'application décide d'un visiteur :

| Partie | Le membre | Un autre observateur |
|---|---|---|
| Exemplaires | Tous, avec leur emplacement | Quand l'étagère leur est montrée, sans leur emplacement |
| Liste de souhaits | Toute, dans l'ordre de la liste | Quand la liste leur est montrée |
| Notes | Toutes | Jamais |

L'application produit le rendu d'une vue, jamais celui de l'étagère elle-même, pour quiconque n'est pas son membre.

## Les jeux qu'un membre peut apporter

`bringable()` donne les jeux que les membres nommés possèdent et peuvent apporter à une soirée de jeux, sous forme de
`BringableGame` : une fois par membre et par jeu, quel que soit le nombre d'exemplaires détenus, membre après membre
dans l'ordre donné, jusqu'à la limite. Elle lit au plus 2000 membres et donne au plus 10 000 jeux à la fois.
Les [points d'extension](extension-points.md#les-étagères-dune-soirée-de-jeux) montrent l'adaptateur du port d'une
soirée de jeux.
