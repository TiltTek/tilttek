<!-- phil-dev source-sha256=43cacb30264d1cba903668bfecb93e940da5965eb96c0a294747ae063e6f46c5 -->
# tilttek/wargame-army

**Des listes d'armée pour des jeux de figurines, sur un moteur générique séparé des catalogues que fournit
l'application : unités, options, nombres de figurines, points, emplacements de détachement et contraintes déclarées,
validées une règle à la fois avec des messages clairs et exportées en texte ou en HTML échappé, sans aucun catalogue
d'éditeur fourni.**

Le moteur ne connaît aucun jeu. Un catalogue apporte les données d'un jeu : ses unités avec leurs catégories, leurs
points, leurs figurines et leurs groupes d'options, ses types de détachement avec leurs emplacements par catégorie, le
nombre de détachements que prend une armée, et les contraintes qu'il déclare — *au moins un chef*, *au plus un monstre*,
*les héros coûtent au plus 25 % des points*. Une liste d'armée nomme son catalogue et la version avec laquelle elle a
été construite, sa limite de points, et ses détachements d'unités avec leurs figurines et leurs options.

Le validateur vérifie une liste selon son catalogue, une règle déclarée à la fois, et retourne toutes les violations à
la fois, chacune avec une phrase que les gens lisent et les valeurs qui la fondent : *Detachment 2 "Raiders", unit 1
"Scouts": 11 models; the unit takes 5 to 10.* Les points de chaque unité, de chaque détachement et de la liste
l'accompagnent. Une application ajoute ses propres règles — le plafond de points d'un club, les restrictions d'un
tournoi — comme des contraintes à elle. L'exportation écrit la liste en texte brut ou en fragment de HTML échappé, pour
la lire, la coller ou l'imprimer.

Les catalogues appartiennent à l'application, derrière un port : la bibliothèque n'en fournit aucun. C'est une
bibliothèque PHP pure, sans dépendance autre que PHP, avec un adaptateur en mémoire pour son port, et rien de conservé
entre deux appels.

## État

| Partie | État |
|---|---|
| `Catalogue`, `UnitEntry`, `OptionGroup`, `Option`, `DetachmentType`, `Slot` : les données d'un jeu, lues et écrites en tableaux et en JSON | Implémenté et testé |
| `ArmyList`, `Detachment`, `UnitSelection` : des listes qui changent par de nouvelles listes, lues et écrites en tableaux et en JSON | Implémenté et testé |
| `Costing` : les points de chaque unité, de chaque détachement et de la liste | Implémenté et testé |
| `ArmyValidator`, `Validation`, `Violation`, `Rule` : chaque règle enfreinte, avec un message clair | Implémenté et testé |
| `Constraint\UnitCount`, `Constraint\PointsShare`, `Contract\Constraint` : les contraintes qu'un catalogue déclare, et celles propres à une application | Implémenté et testé |
| `Export\TextExport`, `Export\HtmlExport` : des listes lisibles et imprimables | Implémenté et testé |
| `Contract\CatalogueProvider` et son adaptateur en mémoire | Implémenté et testé |
| Un catalogue d'un jeu quelconque | Non fourni : l'application fournit le sien, comme le dit [Catalogues et licences](#catalogues-et-licences) |
| Une exportation PDF | Non fournie : l'exportation HTML est la source qu'une application imprime ou convertit |

## Catalogues et licences

La bibliothèque n'importe le catalogue d'aucun éditeur, et ne fournit aucune unité, aucune valeur de points et aucune
règle d'un jeu publié. Les données d'armée d'un jeu appartiennent à son éditeur : une application fournit ses propres
catalogues — de son propre jeu, inventés, ou sous une licence qu'elle détient et qui permet cet usage —, par
`Contract\CatalogueProvider`. Les exemples et les tests n'emploient qu'un catalogue inventé.
[Catalogues et licences](doc/fr/catalogues-and-licences.md) dit comment une application fournit son catalogue.

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [Catalogues](doc/fr/catalogues.md) | Unités, options, types de détachement, emplacements et la forme d'un catalogue en JSON. |
| [Listes d'armée et validation](doc/fr/army-lists-and-validation.md) | Les listes, les points, le validateur et ses messages. |
| [Contraintes](doc/fr/constraints.md) | Les comptes et les parts de points qu'un catalogue déclare, et les contraintes propres à une application. |
| [Exportation](doc/fr/export.md) | Le texte et le HTML d'une liste. |
| [Catalogues et licences](doc/fr/catalogues-and-licences.md) | Pourquoi aucun catalogue n'est fourni, et comment une application fournit le sien. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque. |
| [Exceptions](doc/fr/exceptions.md) | `WargameArmyException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, ses cibles de fuzzing et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\WargameArmy\{ArmyList, ArmyValidator, Catalogue, Detachment, UnitSelection};
use Tilttek\WargameArmy\Catalog\InMemoryCatalogueProvider;
use Tilttek\WargameArmy\Export\TextExport;

$catalogue = Catalogue::fromJson(file_get_contents('catalogues/ember-vale.json'));   // the application's own
$validator = new ArmyValidator(new InMemoryCatalogueProvider($catalogue));

$list = ArmyList::for($catalogue, 'list-1', 'Ember Wardens', 500)
    ->withDetachment(new Detachment('vanguard', 'Wardens', [
        new UnitSelection('captain', 1, ['bow' => 1]),
        new UnitSelection('scouts', 6, ['long-rifle' => 1]),
    ]));

$validation = $validator->validate($list);
$validation->isValid();             // true or false
$validation->messages();            // every broken rule, in order
$validation->costing->total;        // the points of the list

echo new TextExport()->render($list, $catalogue, $validation);
```

## Prérequis

PHP 8.5 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
