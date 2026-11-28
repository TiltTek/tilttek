<!-- phil-dev source-sha256=96b49f4dabaf7ca868ca790b2b017888d67847162e99fc82c2ca29216949a004 -->
# tilttek/crafting

**Des recettes d'objets gardées comme des données, qui combinent ou font évoluer des objets et montrent ce qui manque
encore avant qu'on fabrique quoi que ce soit.**

tilttek/crafting transforme des objets en d'autres objets. Un site écrit ses recettes comme des données — un fichier de
configuration, une ligne de base de données —, jamais comme du code : chaque recette liste les ingrédients qu'elle
**consomme**, par le code d'un objet ou par une **étiquette** que tout objet peut porter — `rare-metal` plutôt qu'un
métal précis —, les **outils** qu'il suffit de détenir, les objets qu'elle **produit** et ceux qu'elle **rend**, le seau
vide d'un seau d'eau. Une recette **combine** des objets en de nouveaux objets ou fait **évoluer** un objet vers sa forme
suivante, un œuf de dragon en dragon, qu'un inventaire peut garder comme le même objet.

Avant que quoi que ce soit bouge, un **aperçu** montre, ingrédient par ingrédient, les objets mis de côté et ce qui
manque encore ; le fabricant liste ce qu'un membre peut fabriquer maintenant et ce dont il est le plus **proche**. Une
fabrication se fait **une seule fois par clé**, en entier ou pas du tout : une requête envoyée deux fois fabrique une
seule fois, et rien ne bouge quand un objet manque. Une recette est toujours déterministe, et un objet que le site
protège — un objet acheté avec de l'argent — n'est jamais consommé.

Il s'adresse à un site dont les membres rassemblent des objets — une collection, l'inventaire d'un personnage, l'économie
d'un grandeur nature — et en font quelque chose ; `tilttek/larp-crafting` s'appuie sur lui pour les grandeurs nature.
C'est une bibliothèque PHP simple dont les dépendances sont `phil/resource`, la référence opaque d'un détenteur, et
`psr/clock` ; les objets restent là où l'application les garde, derrière l'`Inventory` qu'elle implémente. Les
détenteurs sont des références que l'application donne : la bibliothèque ne lit jamais un nom, un courriel ni un compte,
et ses événements ne portent que des identifiants.

## État

| Partie | État |
|---|---|
| `Recipe`, `Ingredient`, `ItemQuantity`, `RecipeKind` : des recettes qui combinent ou font évoluer, des ingrédients par objet ou par étiquette, des outils, des produits et des objets rendus | Implémenté et testé |
| `RecipeBook` : les recettes lues depuis des valeurs simples et réécrites, les recettes qui consomment ou produisent un objet | Implémenté et testé |
| `Crafter`, `Plan`, `Requirement`, `Selection` : des aperçus, ce qui est fabricable maintenant et le plus proche, les objets choisis pour une étiquette, des fabrications une seule fois par clé | Implémenté et testé |
| `Craft`, `Crafted`, `Contract\CraftObserver` : la fabrication faite, et ce qui est signalé, identifiants seulement | Implémenté et testé |
| `Contract\Inventory`, `Holding`, avec `Store\InMemoryInventory` | Implémenté et testé |
| `Contract\ConsumptionPolicy`, avec `Policy\ProtectedItems` : les objets qu'aucune fabrication ne consomme | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [Recettes](doc/fr/recipes.md) | Les ingrédients, les outils, les produits et les objets rendus, les recettes qui font évoluer, les attributs, le livre lu depuis des valeurs simples, les objets protégés. |
| [Fabrication](doc/fr/crafting.md) | Les aperçus et ce qui manque, comment les objets sont mis de côté, le choix des objets d'une étiquette, les fabrications une seule fois par clé, ce qui est fabricable et le plus proche. |
| [Points d'extension](doc/fr/extension-points.md) | L'inventaire, la politique de consommation, les observateurs et l'horloge. |
| [Exceptions](doc/fr/exceptions.md) | `CraftingException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests, la cible de fuzzing et le banc d'essai de la bibliothèque, et le test du code qui fabrique. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/crafting
```

```php
use Phil\Resource\ResourceReference;
use Tilttek\Crafting\Crafter;
use Tilttek\Crafting\RecipeBook;
use Tilttek\Crafting\Store\InMemoryInventory;

$book = RecipeBook::fromArray(['recipes' => [
    'healing-potion' => [
        'ingredients' => [
            ['item' => 'red-herb', 'quantity' => 2],
            ['tag' => 'clear-water'],
            ['item' => 'mortar', 'consumed' => false],
        ],
        'outputs' => [['item' => 'healing-potion']],
        'returns' => [['item' => 'empty-bucket']],
    ],
]]);

$inventory = new InMemoryInventory(['rain-water' => ['clear-water']]);
$crafter = new Crafter($book, $inventory, $clock);
$member = ResourceReference::of('member', '42');

$inventory->put($member, 'red-herb', 1);
$inventory->put($member, 'rain-water', 1);

$plan = $crafter->preview($member, 'healing-potion');
$plan->isCraftable();                       // false
$plan->missing();                           // 1 × red-herb, 1 × mortar

$inventory->put($member, 'red-herb', 1);
$inventory->put($member, 'mortar');

$crafter->craft($member, 'healing-potion', 'craft-7f3a');   // the herbs and the water are taken, the mortar stays
```

## Prérequis

PHP 8.5 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
