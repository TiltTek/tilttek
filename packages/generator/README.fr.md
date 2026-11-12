<!-- phil-dev source-sha256=4e31498b9382da47e081dffa326a1c7b4d059d736f8159bc7bed6d69acb67a19 -->
# tilttek/generator

**Des générateurs aléatoires écrits comme des données : des listes, et des gabarits Twig qui y puisent.**

tilttek/generator tire des noms, des personnages, des auberges, des trésors, des rumeurs ou des cartes de listes et de
gabarits qu'un auteur écrit en YAML ou en JSON, sans code. Une liste contient des entrées pondérées, avec des champs et
des étiquettes. Un gabarit est une expression Twig dans un bac à sable : il puise dans les listes selon le poids et
l'étiquette, appelle d'autres générateurs, lance des dés et façonne le texte avec les filtres de Twig et la grammaire
d'une langue. Cette grammaire couvre l'élision et la contraction (`le arbre` → `l'arbre`, `de el
rey` → `del rey`, `di il re` → `del re`), les articles selon le genre et le nombre, l'accord et le pluriel en français,
en anglais, en espagnol, en allemand, en italien et en portugais, et toute autre langue qu'une application ajoute
derrière le port `Grammar`. Un générateur retourne un texte, un résultat structuré (une auberge avec son nom, son
aubergiste, son personnel et son menu) ou une carte (cases carrées, hexagones, ou salles reliées par des passages). Une
liste peut inventer des mots nouveaux à partir de ses entrées par une chaîne de Markov au lieu de les rendre ; un
générateur peut porter un état d'un tirage au suivant (une table à mémoire, pour la météo d'une campagne, un voyage ou
l'exploration d'une carte), que l'appelant garde et repasse. Des tables aléatoires prêtes à tirer sont livrées dans
`tables/`, en anglais et en français : rencontres, objets magiques, butins avec objets non identifiés, boutiques,
accroches de scénario, rumeurs, oracle du jeu en solo et bonne aventure. Il ne garde de listes ni d'états pour personne, n'affiche pas de résultats et
ne les sert pas : l'application tient ses propres listes, ses états et ses pages, et le bundle sert les tirages.

Elle s'adresse à tout code PHP qui crée du contenu aléatoire pour des gens : un générateur de noms, la boîte à outils
d'un meneur de jeu, un oracle de jeu en solo, une table aléatoire dans un livre, un donjon pour une table virtuelle.
`tilttek/generator-bundle` l'intègre à Symfony ; une application sans Symfony prend la bibliothèque seule.

C'est une bibliothèque PHP construite sur Twig, dont le bac à sable exécute les gabarits, sur Symfony Yaml, qui lit les
fichiers, et sur `tilttek/dice`, qui lance les dés. Son hasard est un `Random\Randomizer` que passe l'appelant, ou le
germe d'un tirage, qui fait revenir le même tirage. Chaque gabarit est compilé et vérifié à la construction du
catalogue. Un gabarit qui ne compile pas, sort du bac à sable ou nomme une liste, une étiquette ou un générateur
inexistants est alors une erreur, et non un texte vide plus tard.

La conception est sobre : un `Reader` ou un `FileLoader` donne un `Catalog` d'`EntryList` et de
`GeneratorDefinition`, un `Generator` le vérifie et y fait des tirages qui donnent un `DrawResult`, et les classes
`Grammar` fournissent les filtres de chaque langue ; `Tables` mène aux tables que livre la bibliothèque.

## État

| Partie | État |
|---|---|
| Listes : entrées pondérées, champs, étiquettes ; documents en YAML et en JSON, un ou plusieurs par fichier ; erreurs avec leur emplacement | Implémentées et testées |
| Listes de Markov : mots inventés à partir des entrées, ordre et longueurs, entrées écartées, étiquettes du tirage | Implémentées et testées |
| Tables à mémoire : un état donné par des gabarits de départ et suivants, passé d'un résultat à l'autre et retourné | Implémentées et testées |
| Tables aléatoires dans `tables/` : rencontres, objets magiques, butins, boutiques, accroches, rumeurs, oracle du jeu en solo, bonne aventure, en anglais et en français | Implémentées et testées |
| Gabarits dans le bac à sable de Twig : `pick` selon le poids, l'étiquette, plusieurs sans doublons, avec exclusions ; `generate` ; `roll` | Implémentés et testés |
| Vérifications à la construction du catalogue : syntaxe, bac à sable, listes, étiquettes et générateurs inconnus, opérateur d'intervalle | Implémentées et testées |
| Variables partagées par un résultat ; résultats structurés ; cartes carrées et hexagonales ; graphes de nœuds et de passages | Implémentés et testés |
| Filtres de grammaire : élision, contraction, articles, accord et pluriel en français ; `a`/`an`, articles et pluriel en anglais | Implémentés et testés |
| Grammaires espagnole, allemande, italienne et portugaise : articles selon le genre et le nombre, contractions et élision, féminin et pluriel | Implémentées et testées |
| Le port `Grammar`, par lequel une application ajoute ou remplace une langue | Implémenté et documenté |
| Tirages : nombres, résultats uniques avec un nombre borné de tentatives, germes ; limites de profondeur, de tirages, de temps, de résultats et de cases | Implémentés et testés |
| Exceptions sous une seule interface `GeneratorException` | Implémentées et testées |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis et installation. |
| [Listes et générateurs](doc/fr/lists-and-generators.md) | Les documents : listes, entrées, champs, étiquettes, listes de Markov, générateurs et leurs genres. |
| [Gabarits](doc/fr/templates.md) | `pick`, `generate`, `roll`, les variables, les filtres et le bac à sable. |
| [Grammaire](doc/fr/grammar.md) | Élision, articles, accord et pluriel en français, en anglais, en espagnol, en allemand, en italien et en portugais ; ajouter une langue. |
| [Cartes](doc/fr/maps.md) | Cartes carrées et hexagonales, graphes, leurs coordonnées et leurs résultats. |
| [Tirages](doc/fr/drawing.md) | `Generator`, `Draw`, `DrawResult`, les états, l'unicité, les germes, le hasard et les limites. |
| [Tables à mémoire](doc/fr/memory.md) | Un état porté d'un tirage au suivant : l'écrire, tirer avec lui, et les exemples de la météo, du voyage et de l'exploration d'hexagones. |
| [Tables aléatoires](doc/fr/tables.md) | Les tables de `tables/` en anglais et en français, ce que donne chacune, et l'oracle du jeu en solo. |
| [Points d'extension](doc/fr/extension-points.md) | Le port de grammaire, l'horloge, le hasard et les dés que prend un générateur. |
| [Exceptions](doc/fr/exceptions.md) | `GeneratorException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation, et comment tester du code qui fait des tirages. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

Le répertoire `examples/` contient des exemples neutres : des noms syllabiques, des noms inventés par une chaîne de
Markov, un oracle oui ou non, une auberge française, un village, un donjon, et trois tables à mémoire : la météo en fleur
d'hexagones, un voyage et une exploration d'hexagones.

## Démarrage rapide

```sh
composer require tilttek/generator
```

```yaml
# generators/inn.yaml
lists:
  inn.animal:
    - { text: sanglier, gender: m }
    - { text: oie, gender: f }
  inn.adjective:
    - { text: doré }
    - { text: vieux, f: vieille }
generators:
  inn.name:
    locale: fr
    templates:
      - "{% set animal = pick('inn.animal') %}{{ ('à ' ~ animal|article ~ ' ' ~ pick('inn.adjective')|agree(animal))|elide }}"
    filters: [upper_first]
```

```php
use Tilttek\Generator\Draw;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Loading\FileLoader;

$generator = new Generator(new FileLoader()->catalog(['generators']));

$generator->draw('inn.name', new Draw(3, unique: true))->results;   // ["À l'oie dorée", 'Au sanglier vieux', …]
$generator->draw('inn.name', new Draw(seed: 'table 7'))->first();   // the same text for the same seed
```

## Prérequis

PHP 8.5 ou plus récent, avec l'extension `mbstring`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
