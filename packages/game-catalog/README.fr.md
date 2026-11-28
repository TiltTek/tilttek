<!-- phil-dev source-sha256=48bf42df9caf898f4670d461465da475dc488c9dc66efdbdea54886583cbec44 -->
# tilttek/game-catalog

**Gardez un seul catalogue de jeux de société et de jeux de rôle : identifiants de bases ouvertes, errata,
corrections de membres gardées à part et collections importées par fichier.**

tilttek/game-catalog garde les jeux qu'une application montre, une entrée par jeu, pour que les soirées de jeux, les
recherches de joueurs et les étagères des membres parlent tous du même jeu. Une entrée contient ce que l'éditeur
annonce — nom, type, genre, système de jeu, éditeurs, concepteurs, année, nombres de joueurs, durée de jeu, âge
minimal, langues, niveaux d'un scénario, dépendance à la langue, description, couverture — et, à côté :

- les identifiants du jeu dans des bases ouvertes : BoardGameGeek et RPGGeek, dont le catalogue garde l'identifiant et
  construit le lien, sans rien copier d'autre ; Wikidata, dont les données sont CC0, comme ancre libre ;
- ses errata, dans l'ordre où ils ont été publiés, avec un observateur informé de chacun, pour les membres qui suivent
  le jeu ;
- les corrections des membres, sur n'importe quel champ, gardées à côté de l'entrée jusqu'à ce que l'administration les
  accepte ou les refuse ; une fois acceptées, gardées à côté des valeurs de la source, pour que la source qui renvoie
  l'entrée ne les défasse pas ;
- les votes des membres, montrés à côté des valeurs de l'éditeur et jamais à leur place : pour chaque nombre de
  joueurs, une note de 1 à 5 et l'un des trois choix — idéal, recommandé, non recommandé —, ainsi que le poids, l'âge
  minimal et la dépendance à la langue ;
- ses liens avec d'autres entrées — extension, réimplémentation, édition, traduction et les autres du vocabulaire
  d'OpenTabletop —, et les codes-barres de ses boîtes, pour qu'une boîte numérisée une fois soit identifiée localement
  la fois suivante ;
- son historique.

Les entrées sont saisies à la main ou envoyées par des sources, les ports qui alimentent le catalogue : le catalogue
garde chaque entrée qu'on lui a envoyée et se passe de tout service tiers. Un membre importe sa collection depuis le
fichier qu'il exporte — les jeux de rôle d'abord —, appariée par identifiant ; on ne lit du fichier que les
identifiants, les statuts, la priorité d'un souhait et les parties jouées. L'expérience d'un critique — jeux joués et
animés — est figée à côté de chaque critique par un port vers le système de critiques de l'application.

C'est une bibliothèque PHP simple : des services, des ports, des objets-valeurs et un entrepôt gardé en mémoire pour
les tests. Un bundle Symfony, avec l'entrepôt Doctrine, la synchronisation par Messenger, la file des corrections et
les composants Twig de la page d'un jeu, viendra plus tard.

## État

| Partie | État |
|---|---|
| `Details`, `Entry`, `Origin`, `ExternalId`, `Gtin`, `Erratum`, `Link` : une entrée, ses identifiants, ses codes-barres, ses errata et ses liens | Implémenté et testé |
| `Catalog` : entrées à la main et par des sources, identifiants, errata, liens, codes-barres identifiés localement puis par un port, masquage, la page d'un jeu, l'historique | Implémenté et testé |
| `Corrections` : corrections proposées sur n'importe quel champ, acceptées avec les conflits montrés, refusées avec une note, champs rendus à leur source | Implémenté et testé |
| `Polls`, `Ballot`, `PollResults` : les notes et les trois choix par nombre de joueurs, le poids, l'âge, la dépendance à la langue | Implémenté et testé |
| `Reviews` : l'expérience d'un critique, figée | Implémenté et testé |
| `Import\CollectionReader`, `Import\CollectionImporter` : un fichier de collection lu dans ses bornes, et apparié au catalogue | Implémenté et testé |
| `Store\InMemoryCatalogStore` : l'entrepôt des tests | Implémenté et testé |
| Un bundle Symfony : l'entrepôt Doctrine, Messenger, la file des corrections, les composants Twig | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Entrées et sources](doc/fr/catalogue.md) | Entrées, sources, identifiants, errata, liens, codes-barres, masquage et page d'un jeu. |
| [Corrections](doc/fr/corrections.md) | Proposer, accepter, refuser et rendre un champ à sa source. |
| [Votes](doc/fr/polls.md) | Les bulletins, les résultats, et leur place à côté des valeurs de l'éditeur. |
| [Import de collection](doc/fr/import.md) | Le fichier qu'un membre exporte, ses bornes et la façon dont on y trouve ses jeux. |
| [Vocabulaire](doc/fr/vocabulary.md) | Les termes d'OpenTabletop que la bibliothèque reprend, et ceux qu'elle ajoute. |
| [Points d'extension](doc/fr/extension-points.md) | L'entrepôt, les sources, le résolveur de codes-barres, l'observateur, les parties et les critiques. |
| [Exceptions](doc/fr/exceptions.md) | `CatalogException` et ses exceptions. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et sa cible de fuzzing. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\GameCatalog\Catalog;
use Tilttek\GameCatalog\Corrections;
use Tilttek\GameCatalog\Details;
use Tilttek\GameCatalog\ExternalId;
use Tilttek\GameCatalog\GameKind;
use Tilttek\GameCatalog\IdentifierScheme;
use Tilttek\GameCatalog\Range;

$catalog = new Catalog($store, $clock);              // your CatalogStore and PSR-20 clock
$catalog->add('lanterns', new Details(
    'Lanterns',
    kind: GameKind::RolePlaying,
    system: 'lanterns',
    players: new Range(3, 6),
    languages: ['fr'],
), [ExternalId::rpgGeek(100), ExternalId::wikidata('Q100')]);

$corrections = new Corrections($store, $clock);
$corrections->propose('c-1', 'lanterns', 'm-104', new Details('Lanterns', kind: GameKind::RolePlaying,
    system: 'lanterns', players: new Range(2, 6), languages: ['fr']));
$corrections->accept('c-1', 'admin-1');

$sheet = $catalog->sheet('lanterns');
$sheet->details()->players;                           // 2 to 6, as corrected
$sheet->results->bestByChoice();                      // the members' best numbers of players
$sheet->entry->identifier(IdentifierScheme::RpgGeek)?->link();
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `mbstring`, et `psr/clock`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE). Le
vocabulaire des types de jeux et des liens suit le modèle de données d'OpenTabletop, CC-BY-4.0, comme
[le vocabulaire](doc/fr/vocabulary.md) le mentionne.
