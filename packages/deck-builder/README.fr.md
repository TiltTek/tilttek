<!-- phil-dev source-sha256=41b6d624a50443b6e60ca6fc98c448cca426fad0e64dc799ad54443d73b10879 -->
# tilttek/deck-builder

**Des paquets de cartes construits selon les règles déclarées d'un format — tailles, exemplaires, cartes bannies et
restreintes, listes tabou datées —, validés avec chaque violation lisible, versionnés avec leurs différences, copiés
avec un lien vers l'original et limités aux cartes que possède une collection, sans aucun catalogue d'éditeur
fourni.**

Un format déclare ses règles : le plus petit et le plus grand paquet, les exemplaires permis de chaque carte, les
cartes qu'il bannit ou restreint, et des listes tabou qui les modifient à partir d'un jour donné. La bibliothèque
vérifie un paquet selon les règles qui s'appliquent le jour demandé — le jour d'un tournoi, ou aujourd'hui — et
retourne toutes les violations à la fois, chacune avec une phrase que les gens lisent et les valeurs qui la
fondent : *The deck has 3 copies of "Ember Fox" (fox); the taboo list "autumn" from 2026-10-01 restricts it to 2.*
Avec une collection, elle vérifie aussi le paquet, ou le réduit, aux exemplaires que la collection possède.

Chaque changement d'un paquet est enregistré comme une nouvelle version, jamais réécrite : l'historique les liste et
dit les différences entre deux d'entre elles. Une copie commence un nouveau paquet qui renvoie à la version dont il
a été copié.

Les cartes, les formats et les collections appartiennent à l'application, derrière des ports : la bibliothèque ne
fournit aucune donnée de cartes. C'est une bibliothèque PHP pure, sans dépendance autre que PHP, avec des adaptateurs
en mémoire pour ses ports, et rien de conservé entre deux appels.

## État

| Partie | État |
|---|---|
| `Format`, `TabooList`, `Ruling` : tailles, exemplaires, cartes bannies et restreintes, listes tabou datées, lus et écrits en tableaux et en JSON | Implémenté et testé |
| `Deck`, `DeckReference` : cartes dans l'ordre où elles ont été ajoutées, changements par de nouveaux paquets, copies, limites à une collection, lus et écrits en tableaux et en JSON | Implémenté et testé |
| `DeckValidator`, `Validation`, `Violation`, `ViolationType` : chaque violation, lisible, un jour donné, selon une collection | Implémenté et testé |
| `Decks`, `DeckVersion`, `DeckDiff` : versions jamais réécrites, historique, différences, copies | Implémenté et testé |
| `Contract\CardCatalog`, `Contract\FormatCatalog`, `Contract\OwnedCards`, `Contract\DeckStore` et leurs adaptateurs en mémoire | Implémenté et testé |
| Les données de cartes d'un jeu | Non fournies : l'application fournit les siennes, comme le dit [Catalogues et licences](#catalogues-et-licences) |
| Un adaptateur de base de données | Non fourni : une application implémente `Contract\DeckStore` |

## Catalogues et licences

La bibliothèque n'importe le catalogue d'aucun éditeur, et ne fournit aucune liste de cartes, aucun format ni aucune
liste tabou d'un jeu publié. Un catalogue de cartes est une donnée que son éditeur possède : une application fournit le
sien — des cartes qu'elle a écrites, des cartes inventées, ou des cartes sous une licence qu'elle détient et qui permet
cet usage —, par `Contract\CardCatalog` et `Contract\FormatCatalog`. Les exemples et les tests n'emploient que des
cartes inventées. [Catalogues et licences](doc/fr/catalogues-and-licences.md) dit comment une application fournit son
catalogue.

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [Formats et règles](doc/fr/formats-and-rules.md) | Tailles, exemplaires, cartes bannies et restreintes, listes tabou, et les règles d'un jour. |
| [Construire et valider des paquets](doc/fr/building-and-validating.md) | Les paquets, le validateur, ses violations et les collections. |
| [Historique et copies](doc/fr/history-and-copies.md) | Versions, historique, différences et copies. |
| [Catalogues et licences](doc/fr/catalogues-and-licences.md) | Pourquoi aucun catalogue n'est fourni, et comment une application fournit le sien. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque. |
| [Exceptions](doc/fr/exceptions.md) | `DeckBuilderException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, ses cibles de fuzzing et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\DeckBuilder\{Card, Deck, DeckValidator, Decks, Format, TabooList};
use Tilttek\DeckBuilder\Catalog\{InMemoryCardCatalog, InMemoryFormatCatalog, OwnedCardList};
use Tilttek\DeckBuilder\Store\InMemoryDeckStore;

$validator = new DeckValidator(
    new InMemoryCardCatalog(new Card('fox', 'Ember Fox'), new Card('den', 'Fox Den', copyLimit: 1)),
    new InMemoryFormatCatalog(new Format('league', 'League', minSize: 40, maxSize: 60, copies: 3,
        tabooLists: [new TabooList('autumn', new DateTimeImmutable('2026-10-01'), restricted: ['fox' => 2])],
    )),
);

$deck = new Deck('deck-1', 'Foxes', 'league')->withCard('fox', 3)->withCard('den', 2);
$validation = $validator->validate($deck, new DateTimeImmutable('2026-11-14'), new OwnedCardList(['fox' => 3]));
$validation->isValid();    // false
$validation->messages();   // "The deck has 5 cards; the format "league" needs at least 40.", …

$decks = new Decks(new InMemoryDeckStore());
$decks->save($deck, new DateTimeImmutable(), 'first draft');               // version 1
$decks->save($deck->withoutCard('den'), new DateTimeImmutable());          // version 2
$decks->diff('deck-1', 1, 2)->lines();                                     // ['den: 2 → 1']
$decks->copy('deck-1', 'deck-2', 'My foxes', new DateTimeImmutable());     // links to deck-1, version 2
```

## Prérequis

PHP 8.5 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
