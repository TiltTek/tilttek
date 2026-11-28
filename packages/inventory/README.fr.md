<!-- phil-dev source-sha256=9724ac26378116d79f206a3456b727396e3387168ada579fd64d41ff6f0e66b1 -->
# tilttek/inventory

**Suivez ce que les personnages et les groupes portent en jeu : quantités d'objets, contenants, qui tient quoi, poids
ou emplacements selon les règles du système, et un journal des changements.**

tilttek/inventory tient les inventaires d'un jeu. L'application ouvre un inventaire sous une référence qu'elle donne,
`inventory:7`, pour un propriétaire — un personnage, `character:12`, ou un groupe, `party:3` —, avec la clé de son
système de jeu, `srd-5.2`. Chaque **objet** vit sous une clé, `rope`, `arrows.silver`, avec un nom, une quantité et un
**encombrement** : ce que pèse une unité, dans l'unité entière que choisit le système, et les emplacements qu'occupe
une pile, de sorte qu'un même objet sert les systèmes qui comptent le poids et ceux qui comptent les emplacements.

Un objet est quelque part : dans la **réserve** — le campement, la charrette, la banque, porté par personne —, sur un
**porteur**, sous la référence que lui donne l'application, ou dans un **contenant** du même inventaire. Un contenant
est un objet qui en contient d'autres dans ses limites de poids et d'emplacements, s'emboîte dans d'autres contenants
jusqu'à une profondeur bornée, et peut faire que ce qu'il contient ne pèse rien, comme un sac magique. Les objets
passent d'un lieu à l'autre, une pile entière ou en partie sous une nouvelle clé, un contenant avec ce qu'il contient ;
un porteur équipe ce qu'il tient directement ; un objet passe à un autre inventaire par un transfert — la part du butin
du groupe qui revient à un personnage, l'équipement laissé à l'auberge.

La **charge** d'un porteur suit les **règles de transport** que l'application déclare pour chaque système : ce qu'elles
comptent, le poids ou les emplacements, leurs paliers de charge en fractions de la capacité du porteur — sans
encombrement jusqu'au tiers, encombré jusqu'aux deux tiers —, et si un porteur peut dépasser le dernier palier. La
capacité de chaque porteur vient de l'application, lue sur la fiche du personnage, ou de la valeur par défaut des
règles.

Chaque acte écrit une ligne dans le **journal** de l'inventaire : ce qui a changé, l'objet, combien d'unités, d'où et
vers où, qui a agi, pourquoi et quand, les plus récentes étant conservées dans une limite.

Le paquet sert une table virtuelle, un gestionnaire de personnages ou un site de jeu. C'est une bibliothèque PHP simple
dont les dépendances sont `phil/resource`, la référence opaque d'un inventaire, d'un propriétaire ou d'un porteur, et
`psr/clock`. Les porteurs et les propriétaires sont des références que donne l'application : la bibliothèque ne lit
jamais un nom, un courriel ni un compte, et ses événements ne portent que des identifiants.

## État

| Partie | État |
|---|---|
| `Inventory`, `Inventories` : ouvrir, renommer, changer de système, fermer, lire les inventaires d'un propriétaire, effacer une référence | Implémenté et testé |
| `Item`, `Burden`, `Place` : les objets, leurs quantités, leurs encombrements et leurs lieux ; ajouter, augmenter, diminuer, retirer | Implémenté et testé |
| `Container`, `Fill` : les contenants, leurs limites, leur emboîtement et les contenus qui ne pèsent rien | Implémenté et testé |
| Déplacer une pile entière ou en partie, équiper, transférer à un autre inventaire | Implémenté et testé |
| `CarryRules`, `LoadBand`, `Measure`, `Load` : la charge de chaque porteur selon les règles du système | Implémenté et testé |
| `LogEntry` : le journal de chaque changement, borné | Implémenté et testé |
| `Contract\InventoryStore`, avec `Store\InMemoryInventoryStore` | Implémenté et testé |
| `Contract\RuleBook`, `Contract\CarrierCapacity`, avec `Rules\StaticRuleBook` et `Rules\DefaultCapacity` | Implémenté et testé |
| `Contract\InventoryObserver`, `InventoryChanged` : ce qui est communiqué, des identifiants seulement | Implémenté et testé |
| `Limits` : inventaires par propriétaire, objets par inventaire, profondeur des contenants, entrées du journal | Implémenté et testé |
| Un bundle Symfony : dépôt Doctrine, services, formulaires et traductions | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [Objets et lieux](doc/fr/items.md) | Les inventaires, les objets, les encombrements, les lieux, les contenants, le déplacement, l'équipement et le transfert. |
| [Règles de transport et charges](doc/fr/carrying.md) | Ce que compte un système, les paliers de charge, les capacités, les charges et le remplissage des contenants. |
| [Le journal et l'effacement](doc/fr/log.md) | Le journal de chaque changement, les événements et l'effacement d'une référence. |
| [Points d'extension](doc/fr/extension-points.md) | Le dépôt, le livre de règles, la capacité, les observateurs, les limites et l'horloge. |
| [Exceptions](doc/fr/exceptions.md) | `InventoryException` et ses exceptions, avec les refus et leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, et le test du code qui utilise des inventaires. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/inventory
```

```php
use Phil\Resource\ResourceReference;
use Tilttek\Inventory\Burden;
use Tilttek\Inventory\CarryRules;
use Tilttek\Inventory\Container;
use Tilttek\Inventory\Inventories;
use Tilttek\Inventory\LoadBand;
use Tilttek\Inventory\Measure;
use Tilttek\Inventory\Place;
use Tilttek\Inventory\Rules\StaticRuleBook;
use Tilttek\Inventory\Store\InMemoryInventoryStore;

$rules = new StaticRuleBook([
    'srd-5.2' => new CarryRules(Measure::Weight, 150, [
        new LoadBand('unencumbered', 1, 3),
        new LoadBand('encumbered', 2, 3),
        new LoadBand('heavily-encumbered', 1),
    ]),
]);
$inventories = new Inventories(new InMemoryInventoryStore(), $clock, $rules);

$inventory = ResourceReference::of('inventory', '7');
$ada = ResourceReference::of('character', '12');
$bo = ResourceReference::of('character', '13');

$inventories->open($inventory, ResourceReference::of('party', '3'), 'The party\'s gear', 'srd-5.2');
$inventories->add($inventory, 'pack', 'Backpack', 1, new Burden(5), Place::carriedBy($ada), new Container(maxWeight: 30));
$inventories->add($inventory, 'rope', 'Hempen rope', 1, new Burden(10), Place::inside('pack'));
$inventories->add($inventory, 'arrows', 'Arrows', 20, new Burden(1, 1, 20), Place::carriedBy($ada));

$inventories->move($inventory, 'arrows', Place::carriedBy($bo), quantity: 10, as: 'arrows.bo');
$inventories->decrease($inventory, 'arrows', 3, reason: 'shot at the goblins');

$inventories->load($inventory, $ada)?->band?->key;   // 'unencumbered': 5 + 10 + 7
```

## Prérequis

PHP 8.5 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
