<!-- phil-dev source-sha256=51043f8085b22d64638af8f79f7e679193451f233205e5010e26165f0b319f31 -->
# tilttek/collections

**Des objets à collectionner — cartes, dés, figurines, costumes —, leur rareté et leurs ensembles, l'objet du jour et
des paquets dont les chances sont publiées.**

tilttek/collections garde ce que collectionnent les membres d'un site. Un catalogue déclare des raretés — `common`,
`rare`, `legendary` — avec leurs poids, des objets avec un nom et une description traduits, une image, une rareté et
les ensembles auxquels ils appartiennent, des ensembles qui valent leurs récompenses déclarées une fois qu'un membre en
possède chaque objet, et des paquets. Les exemplaires d'un membre arrivent sous une clé qui fait compter chacun une
seule fois : les ensembles de base à l'arrivée, un ensemble gagné contre des points ou acheté en entier, une
récompense, un cadeau, l'objet du jour — un objet par jour, tiré d'un calendrier calculé à partir de dates fixes, de
dates selon les règles propres à l'application et d'un tirage pondéré par la rareté les jours libres, remis sur
l'activité des membres enregistrée par `tilttek/activity` —, ou un paquet, plusieurs objets tirés selon la rareté avec
des chances publiées, à partir d'un germe engagé par le site et d'un germe du client donné par le membre, qui permettent
à quiconque de rejouer le tirage. Les doubles sont comptés, l'album affiche
*12 sur 20*, et un ensemble complété pour la première fois est annoncé avec ses récompenses. Un membre choisit lesquels
de ses dés affiche le lanceur de dés, les exemplaires passent d'un membre à l'autre lors d'un échange, et effacer un
membre supprime ce que les collections gardaient de lui.

Elle s'adresse à un site qui donne à ses membres quelque chose à collectionner — les mascottes costumées d'un site
communautaire, des cartes à échanger virtuelles, les dés d'un ensemble. `tilttek/collections-bundle` l'intègre à
Symfony et garde le tout dans Doctrine DBAL. Une application sans Symfony prend la bibliothèque seule et écrit ses
propres stockages, ou utilise ceux en mémoire.

C'est une bibliothèque PHP simple. Ses dépendances sont `tilttek/activity`, le journal sur lequel se réclame l'objet du
jour, `phil/resource`, la référence opaque d'un membre, `psr/clock`, et les extensions `mbstring` et `sodium`. Les
membres sont des références que donne l'application, `member:44` : la bibliothèque ne lit jamais un nom, un courriel ou
un compte. Elle ne dépend d'aucun autre paquet de ludification : les récompenses sont déclarées et l'application les
remet, et `tilttek/exchange` atteint les exemplaires par l'inventaire que fournit le bundle.

La conception est petite : un `Catalog` contient des définitions `Rarity`, `Item`, `ItemSet` et `Pack`, lues par
`CatalogReader` à partir de valeurs simples et fournies par un `CatalogProvider`. `Collections` remet des objets `Copy`
sous une `CollectionKey`, les déplace, les reprend et construit l'`Album`. `Packs` engage un `Pack` en une
`PendingOpening`, puis l'ouvre en une `PackOpening` à partir des `Odds`. `DailyCalendar`, `Daily` et `DailyProjection` remettent l'objet du jour.
`DiceShelf` garde les dés qu'a choisis un membre. `MemberData` exporte et efface un membre.

## État

| Partie | État |
|---|---|
| `Names`, `Texts`, `Rarity`, `CollectibleDie`, `Reward`, `Item`, `SetKind`, `ItemSet`, `Pack` : les définitions, traduites, vérifiées | Implémenté et testé |
| `Catalog`, `CatalogReader`, `CatalogProvider`, `FixedCatalog`, `WritableCatalog`, `DefinitionKind`, `Odds` : le catalogue à partir de valeurs simples, ses références vérifiées, les chances d'un tirage et le tirage | Implémenté et testé |
| `CollectionKey`, `Source`, `Copy`, `CopyMove`, `Collections` : des exemplaires remis une seule fois par clé, des ensembles remis en entier, les ensembles de base à l'arrivée, les déplacements d'un échange, les exemplaires repris | Implémenté et testé |
| `Album`, `Holding`, `SetProgress`, `SetCompletion`, `SetCompleted`, `CollectionObserver` : l'album, les doubles, la progression et les ensembles complétés une seule fois avec leurs récompenses | Implémenté et testé |
| `Pack\Packs`, `Pack\PendingOpening`, `Pack\PackOpening`, `Pack\SeedSource`, `Pack\SecureSeeds` : des paquets engagés, puis ouverts à partir du germe du site et du germe du client du membre, des chances publiées et la vérification | Implémenté et testé |
| `Daily\DailyCalendar`, `Daily\DateRule`, `Daily\FixedDate`, `Daily\DailyPick`, `Daily\PickOrigin`, `Daily\Days` : le calendrier calculé de l'objet du jour, généré et corrigé | Implémenté et testé |
| `Daily\Daily`, `Daily\DailyProjection` : l'objet du jour réclamé une seule fois par membre et par jour sur l'activité, les annulations et les reconstructions | Implémenté et testé |
| `Dice\DiceShelf`, `Dice\DieChoice` : les dés que possède un membre et celui qu'il lance pour chaque nombre de faces | Implémenté et testé |
| Les cinq stockages avec leurs implémentations en mémoire et les contrats que respecte tout stockage | Implémenté et testé |
| `MemberData`, `Erasure` : l'export et l'effacement d'un membre | Implémenté et testé |
| Exceptions sous une seule interface `CollectionsException` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Le catalogue](doc/fr/catalog.md) | Les raretés, les objets, les ensembles, les paquets, la définition que contient une configuration, et un catalogue gardé dans une base de données. |
| [Les exemplaires et l'album](doc/fr/copies-and-album.md) | Les clés, les sources, les exemplaires liés, les ensembles remis en entier, les ensembles de base, l'album, les doubles, les ensembles complétés et leurs récompenses, les déplacements et les exemplaires repris. |
| [L'objet du jour](doc/fr/item-of-the-day.md) | Le calendrier calculé, ses règles et son tirage, sa génération et sa correction, la réclamation de l'objet, la projection. |
| [Paquets](doc/fr/packs.md) | Les chances publiées, l'ouverture d'un paquet, les germes, les engagements et la vérification. |
| [Dés](doc/fr/dice.md) | Les dés d'un ensemble, le dé que choisit un membre, et le lanceur de dés. |
| [Effacement](doc/fr/erasure.md) | Ce que couvrent l'export et l'effacement d'un membre. |
| [Écrire un stockage](doc/fr/writing-a-store.md) | Les cinq contrats de stockage qu'implémente le stockage d'une application. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application fournit à la bibliothèque : le catalogue, les stockages, les règles de dates, les germes, les observateurs, l'horloge. |
| [Exceptions](doc/fr/exceptions.md) | `CollectionsException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation, et comment tester du code qui lit les collections. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/collections
```

```php
use Tilttek\Collections\Collections;
use Tilttek\Collections\FixedCatalog;
use Tilttek\Collections\Source;
use Tilttek\Collections\Store\InMemoryCompletionStore;
use Tilttek\Collections\Store\InMemoryCopyStore;

$catalog = FixedCatalog::fromArray([
    'rarities' => ['common' => 70, 'rare' => 25, 'legendary' => 5],
    'items' => [
        'deckhand' => ['name' => ['en' => 'Deckhand', 'fr' => 'Matelot'], 'rarity' => 'common', 'sets' => ['crew']],
        'captain' => ['name' => 'Captain', 'rarity' => 'legendary', 'sets' => ['crew']],
    ],
    'sets' => ['crew' => ['name' => 'The crew', 'rewards' => [['kind' => 'points', 'code' => 'xp', 'amount' => 50]]]],
]);
$collections = new Collections($catalog, new InMemoryCopyStore(), new InMemoryCompletionStore(), $clock);

$collections->grant($member, 'deckhand', Source::Purchase, 'order:2026-0193:line:1');
$collections->album($member)->set('crew')?->total(); // 2
```

## Prérequis

PHP 8.5 ou plus récent, avec les extensions `mbstring` et `sodium`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
