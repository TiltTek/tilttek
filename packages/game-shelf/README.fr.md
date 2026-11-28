<!-- phil-dev source-sha256=114d72108e985411862f4cc81385b6a8ad1ab91d35a988dd3d7b02b1d03dbb51 -->
# tilttek/game-shelf

**Savoir quels jeux chaque membre possède, échange et désire : des exemplaires avec leur statut, une liste de souhaits
dans l'ordre du membre, des notes privées, l'importation d'une collection et l'exportation en CSV.**

tilttek/game-shelf garde une étagère par membre, sur les jeux de `tilttek/game-catalog` :

- **les exemplaires** — chaque boîte par son propre identifiant, de sorte qu'un membre peut détenir deux éditions d'un
  même jeu, en garder une et offrir l'autre en échange. Un exemplaire est possédé, possédé auparavant, précommandé ou
  à échanger, nomme son édition, dit où il est rangé et s'il peut sortir de la maison pour une soirée de jeux ;
- **les souhaits** — les jeux qu'un membre souhaite, veut acheter, veut en échange ou veut jouer, à un niveau allant de
  « à posséder absolument » à « ne pas acheter », dans un ordre que le membre fixe en déplaçant un souhait avant ou
  après un autre ;
- **les notes** — ce que le membre garde pour lui, à part de la fiche du catalogue : une cote, un commentaire, des
  étiquettes et le nombre de parties jouées.

L'étagère et la liste de souhaits sont privées tant que le membre ne les montre pas aux membres connectés ou à tout le
monde ; les notes et l'emplacement d'un exemplaire restent privés. Une vue ne donne à chaque observateur que ce qu'il
peut voir, et l'exportation en CSV écrit cette vue, avec en texte les cellules qu'un tableur lirait comme des formules.

Un fichier de collection lu par `tilttek/game-catalog` s'importe dans l'étagère : un exemplaire par jeu possédé, les
souhaits avec leur niveau, les parties jouées ; une nouvelle importation après une soirée de jeux ne change que ce que
le fichier a changé, et ne touche jamais aux exemplaires saisis à la main. Une importation synchronisée déplace aussi
vers « possédé auparavant » les exemplaires importés que le fichier ne nomme plus.

Les faits d'une étagère — un exemplaire ajouté ou retiré, offert en échange ou retiré de l'échange, un jeu souhaité ou
qui ne l'est plus — vont à un observateur, chacun une fois, par identifiants seulement. Les jeux qu'un membre peut
apporter à une soirée de jeux se lisent en un appel, pour qu'un port de soirée de jeux l'adapte.

C'est une bibliothèque PHP simple : un service, des objets-valeurs, des ports et un stockage gardé en mémoire pour les
tests.

## État

| Partie | État |
|---|---|
| `Shelf`, `Copy`, `Wish`, `Note` : l'étagère, ses exemplaires, sa liste de souhaits dans l'ordre du membre et ses notes privées | Implémenté et testé |
| `GameShelves` : exemplaires, souhaits et leur ordre, notes, visibilité, importation et jeux que les membres peuvent apporter, avec leurs événements | Implémenté et testé |
| `ShelfView` et `Export\ShelfCsv` : ce que voit chaque observateur, et son exportation en CSV | Implémenté et testé |
| `Store\InMemoryShelfStore` : le stockage des tests | Implémenté et testé |
| Un bundle Symfony : le stockage Doctrine, Messenger, les formulaires et les composants Twig | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Étagères](doc/fr/shelves.md) | Les exemplaires et leurs statuts, les souhaits et leur ordre, les notes, la visibilité et les vues. |
| [Importation et exportation](doc/fr/import-export.md) | Importer un fichier de collection, l'importer de nouveau, synchroniser, et l'exportation en CSV. |
| [Points d'extension](doc/fr/extension-points.md) | Le stockage, l'observateur et ses événements, et l'adaptateur des étagères d'une soirée de jeux. |
| [Exceptions](doc/fr/exceptions.md) | `ShelfException` et ses exceptions. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\GameShelf\Copy;
use Tilttek\GameShelf\CopyStatus;
use Tilttek\GameShelf\Export\ShelfCsv;
use Tilttek\GameShelf\GameShelves;
use Tilttek\GameShelf\Note;
use Tilttek\GameShelf\Viewer;
use Tilttek\GameShelf\Visibility;
use Tilttek\GameShelf\Wish;
use Tilttek\GameShelf\WishLevel;

$shelves = new GameShelves($store, $catalog, $observer);      // your ShelfStore, the Catalog, your ShelfObserver

$shelves->place('member-1', new Copy('copy-1', 'harbour', edition: 'French'));
$shelves->place('member-1', new Copy('copy-2', 'harbour', [CopyStatus::Owned, CopyStatus::ForTrade], 'English'));
$shelves->wish('member-1', new Wish('lanterns', level: WishLevel::MustHave));
$shelves->wish('member-1', new Wish('tide'));
$shelves->moveWishBefore('member-1', 'tide', 'lanterns');      // the member's own order
$shelves->note('member-1', new Note('harbour', rating: 8, plays: 12));
$shelves->share('member-1', Visibility::Members, Visibility::Private);

$view = $shelves->view('member-1', Viewer::Member);            // copies without their location, no wishlist, no notes
$csv = new ShelfCsv()->copies($view);
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-mbstring` et `tilttek/game-catalog`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
