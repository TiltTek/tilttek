<!-- phil-dev source-sha256=baa972582779f6dc7183d129e64c248909cca9b716b1fe4ce4e3bdfb15480804 -->
# tilttek/loot-tables

**Des tables de butin lancées avec n'importe quel dé : engagées avant, révélées après, et vérifiables par tous.**

tilttek/loot-tables décrit ce qu'un produit peut contenir sous forme de tables d'entrées, chacune lancée avec un seul
dé : six entrées avec un d6, huit avec un d8, vingt avec un d20. Chaque entrée a un nom, une image et l'emplacement
qu'elle occupe : une tête, une main, une ceinture. Une boîte déclare les tables qu'un produit lance, dans l'ordre : six
tables de six entrées lancent 6d6. Le tirage est vérifiable. Son germe secret est engagé avant que quiconque connaisse
le résultat, les dés sont lancés à partir de lui par `tilttek/dice`, et la preuve publiée permet à n'importe qui de les
relancer. Des dés physiques, lancés devant un caissier, sont saisis tels qu'ils sont tombés. Un texte tiré d'une liste
est une table d'un seul dé. Un résultat s'écrit sous forme de code de boutique, un caractère par dé, et sous forme de
ligne qu'un reçu affiche et qu'un programme relit.

Elle s'adresse à tout code PHP qui vend ou distribue ce que personne n'a choisi : une boîte à butin d'une boutique, un
objet mystère d'un jeu, un texte aléatoire imprimé sur un produit. `tilttek/loot-tables-bundle` l'intègre à Symfony et
aux options d'une commande de commerce.

C'est une bibliothèque PHP simple qui dépend seulement de `tilttek/dice`.

## État

| Partie | État |
|---|---|
| Entrées, tables de n'importe quel dé, boîtes, catalogues et leur lecteur | Implémenté et testé |
| Tirages vérifiables : tirage en attente, révélation, preuve et vérification | Implémenté et testé |
| Résultats de dés physiques, textes tirés d'une liste | Implémenté et testé |
| Codes de boutique et textes de tirage, écrits et relus, testés par fuzzing | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation et un premier tirage. |
| [Tables et boîtes](doc/fr/tables-and-boxes.md) | `LootEntry`, `LootTable`, `LootBox`, `LootCatalog`, `CatalogReader`, et leurs limites. |
| [Tirer](doc/fr/drawing.md) | `PendingDraw`, `LootDrawer`, `RevealedDraw`, la preuve et sa vérification, les dés physiques. |
| [Codes](doc/fr/codes.md) | `ShopCode` et `DrawText`. |
| [Exceptions](doc/fr/exceptions.md) | `LootException` et ses classes. |
| [Points d'extension](doc/fr/extension-points.md) | Où l'application se branche. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, ses cibles de fuzzing, et comment tester le code qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/loot-tables
```

```php
use Random\Engine\Secure;
use Random\Randomizer;
use Tilttek\LootTables\Code\ShopCode;
use Tilttek\LootTables\Draw\LootDrawer;
use Tilttek\LootTables\Draw\PendingDraw;
use Tilttek\LootTables\Reading\CatalogReader;

$catalog = new CatalogReader()->read($decodedYaml);
$box = $catalog->box('starter');

$pending = PendingDraw::open(new Randomizer(new Secure()));
$commitment = $pending->commitment()->hex;      // shown to the buyer before the payment

// once, after the payment, with the client seed the buyer typed after seeing the commitment
$revealed = new LootDrawer()->reveal($box, $pending, $buyerClientSeed);
ShopCode::write($revealed->result);             // "352614"
$proof = $revealed->proof();                    // published: anyone verifies it
new LootDrawer()->verify($box, $proof);         // true
```

## Prérequis

PHP 8.5 ou plus récent, l'extension `mbstring`, et `tilttek/dice`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
