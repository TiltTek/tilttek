<!-- phil-dev source-sha256=535afe82e4777661d9263ef0cfb3a23351abfe58264795b1b5584d8fe44fc7d4 -->
# tilttek/reference-tables

**Les tables qu'un maître de jeu consulte pendant la partie, trouvées par clé ou par plage, ou lancées avec leur dé,
chacune avec sa source, sa licence et sa page.**

tilttek/reference-tables garde les tables de référence d'un système de jeu comme un site les déclare : l'armure d'un
système trouvée par sa clé, le bonus d'un niveau trouvé par la plage où il tombe, une table de réaction lancée au 2d6,
une table de monstres errants lancée au d100 où `96–00` termine la liste. Une table a des colonnes — des textes en
plusieurs langues, des entiers ou des expressions de dés comme le nombre de créatures rencontrées — et des lignes
trouvées par leur clé ou par la plage de valeurs qu'elles couvrent, `≤2`, `3–5`, `12+`. Une table qui a un dé se lance
avec `tilttek/dice`, avec un modificateur quand le système en ajoute un ; un total au-delà de la table prend sa première
ou sa dernière ligne quand le jeu le dit ; un dé lancé à la table s'entre et trouve sa ligne de la même façon ; les dés
d'une cellule se lancent quand la table le demande. Une recherche trouve un texte dans les noms, les descriptions, les
clés de ligne et les cellules de texte d'un catalogue, sans égard à la casse ni aux accents. Chaque table porte sa
provenance — le titre de sa source, qui la détient, sa licence et l'attribution que la licence demande, tirées de la
liste fermée de `tilttek/rpg-systems` —, le système de jeu auquel elle appartient, une catégorie pour la regrouper à
l'écran et la page où elle est imprimée.

Les tables s'écrivent dans un document YAML ou JSON qui fixe une fois une source, un système de jeu et une langue par
défaut, et laisse chaque table dire les siens ; chaque refus nomme son emplacement, `tables[2].rows[4].range`. Un
lancer est annoncé à un observateur par la clé de la table, la position de la ligne et le total, jamais par son texte.

Elle sert à une application qui montre les tables d'un système de jeu pendant que des gens jouent — un écran de maître
de jeu, une référence des règles, une table virtuelle, un jeu de cartes d'étude des chiffres d'un système. Elle ne tire
pas de contenu de listes pondérées et de gabarits : `tilttek/generator` fait des noms, des auberges et des trésors ; une
table de référence rend la ligne que nomme une valeur ou une clé, comme le livre l'imprime. Elle ne garde les tables
pour personne et ne les montre pas : l'application détient ses documents, met le catalogue en cache et dessine ses
pages.

C'est une bibliothèque PHP simple dont les dépendances sont `tilttek/rpg-systems`, qui donne les provenances, les
licences et les textes par locale, `tilttek/dice`, qui lit et lance les dés, et `symfony/yaml`, qui lit les documents
YAML. Chaque service ne garde rien entre deux appels et sert toutes les requêtes d'un *worker*.

## État

| Partie | État |
|---|---|
| `ReferenceTable`, `Column`, `ColumnKind`, `Row`, `Cell`, `Lookup` : des tables de 32 colonnes et 1 000 lignes au plus, trouvées par clé ou par plage, avec leur source, leur système, leur catégorie et leur page | Implémenté et testé |
| `Range` : `12`, `3–5`, `21+`, `≤0`, `96–00` sur un d100, lu, imprimé, sans chevauchement, trous listés | Implémenté et testé, fuzzé |
| `TableCatalogue` : jusqu'à 2 000 tables, par système et par catégorie, avec les sources qu'une page montre | Implémenté et testé |
| `Rolling\TableRoller`, `Rolling\TableRoll` : une table lancée avec son dé et un modificateur, bornée sur demande ; un dé entré ; les dés d'une cellule lancés | Implémenté et testé |
| `Search\TableSearch`, `Search\SearchHit` : un texte trouvé dans les noms, les descriptions, les clés de ligne et les cellules de texte, sans égard à la casse ni aux accents | Implémenté et testé |
| `Format\TableReader`, `Format\Syntax` : les documents YAML et JSON, chaque refus à son emplacement | Implémenté et testé, fuzzé |
| `Contract\TableObserver`, `Event\TableRolled` : chaque lancer annoncé par des identifiants et des nombres | Implémenté et testé |
| Des exceptions sous une seule interface `ReferenceTablesException` | Implémenté et testé |
| Des tables trouvées par deux clés à la fois, une ligne et une colonne | Non commencé |
| Le bundle Symfony : le catalogue comme service, les composants de l'écran | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Tables](doc/fr/tables.md) | Les tables, les colonnes, les cellules, les lignes, les plages, les consultations et le catalogue. |
| [Le format](doc/fr/format.md) | Le document YAML et JSON des tables d'un site, champ par champ. |
| [Lancer](doc/fr/rolling.md) | Lancer une table, les modificateurs, la borne aux extrémités, les dés entrés à la table, les cellules lancées et l'observateur. |
| [Rechercher](doc/fr/searching.md) | Ce qu'une recherche lit, comment elle trouve et ses bornes. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque, et où la bibliothèque s'arrête. |
| [Exceptions](doc/fr/exceptions.md) | `ReferenceTablesException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, ses cibles de fuzzing et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/reference-tables
```

```php
use Random\Engine\Secure;
use Random\Randomizer;
use Tilttek\ReferenceTables\Rolling\TableRoller;
use Tilttek\ReferenceTables\Format\TableReader;
use Tilttek\RpgSystems\Attribution\AttributionRenderer;

$tables = new TableReader()->load('examples/lanterns.yaml');

$tables->get('armor')->row('plate')->cell('defence')->toInt();          // 5
$tables->get('advancement')->lookup(7)->display('bonus');               // "3"

$roll = new TableRoller()->roll($tables->get('reaction'), new Randomizer(new Secure()), modifier: 1);
$roll->row->display('attitude', 'fr');                                  // "Amical", on a total of 9 to 11

echo new AttributionRenderer()->html($tables->provenances(['reaction']));
```

Le document est [`examples/lanterns.yaml`](examples/lanterns.yaml) : quatre petites tables écrites pour les exemples.

## Prérequis

PHP 8.5 ou une version plus récente, avec `mbstring`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE). La licence
couvre le code de la bibliothèque ; la matière d'une table garde la licence que dit sa provenance.
