<!-- phil-dev source-sha256=00d24f68ebae72a87bc8033f87cee8d236c28a21c8a37418f3d3aabc664492ef -->
# tilttek/advent-calendar

**Un calendrier de l'Avent : une case par jour, une surprise par case, et un jour manqué qui n'est pas perdu.**

tilttek/advent-calendar tient des calendriers de l'Avent pour les membres d'un site. Un calendrier a une case pour
chaque jour à partir de sa première date — vingt-quatre en décembre, douze, ou tout nombre jusqu'à quatre-vingt-douze —
et une surprise par case. Une surprise est une référence à une récompense que l'application accorde : un montant de
points, un insigne, un contenu, un code tiré d'une réserve. Les jours se comptent dans le fuseau horaire du calendrier,
à travers les changements d'heure. Chaque calendrier déclare combien de temps une case reste ouvrable une fois son jour
venu : par défaut jusqu'à la fermeture du calendrier, de sorte qu'un membre qui a manqué un jour ouvre sa case plus
tard sans la perdre ; le jour même seulement ; ou pour quelques jours de rattrapage. Aucune case ne s'ouvre avant son
jour. L'ouverture est idempotente : la première ouverture est enregistrée et envoyée comme un événement à partir duquel
l'application accorde la surprise, et rouvrir la case répond la même ouverture et n'accorde rien deux fois, même quand
deux requêtes se font concurrence. Chaque membre voit les cases dans leur état — verrouillée, ouvrable, ouverte,
manquée —, et une case non ouverte ne révèle jamais sa surprise. Effacer un membre supprime ses ouvertures.

Il s'adresse à une communauté ou à une boutique qui veut un rituel saisonnier sans pression : rien à acheter, aucune
case qui disparaît par défaut, aucun classement de ceux qui ont ouvert en premier. Les récompenses restent dans
l'application, qui sait déjà créditer des points ou décerner un insigne ; le calendrier dit seulement quand.

C'est une bibliothèque PHP simple dont les dépendances sont `phil/resource`, la référence opaque d'un membre,
`psr/clock`, l'horloge où se lisent les jours, et `psr/event-dispatcher`, qui porte les ouvertures jusqu'à
l'application. Les membres sont des références que donne l'application, `member:44` : la bibliothèque ne lit jamais un
nom, un courriel ni un compte.

La conception est petite : un `Calendar` tient les `Surprise` de ses cases, son fuseau horaire, son `OpeningPolicy` et
sa fermeture, et donne à chaque case son `OpeningWindow` ; `AdventCalendar` ouvre une case pour un membre, enregistre
l'`Opening` dans un `OpeningStore`, envoie `BoxOpened` une seule fois et montre les cases comme des `BoxView`.

## État

| Partie | État |
|---|---|
| `Calendar` : des cases par jour à partir d'une première date, dans un fuseau horaire, fermant à la dernière case ou plus tard | Implémenté et testé |
| `Surprise`, `SurpriseKind` : des points, un insigne, un contenu ou un code, comme références | Implémenté et testé |
| `OpeningPolicy`, `PolicyKind`, `OpeningWindow` : jusqu'à la fermeture par défaut, le jour même, ou un rattrapage de 1 à 31 jours | Implémenté et testé |
| `AdventCalendar` : ouvertures idempotentes, cases telles qu'un membre les voit, effacement | Implémenté et testé |
| `BoxOpened` : la première ouverture annoncée une fois, avec une clé pour un octroi idempotent | Implémenté et testé |
| `OpeningStore`, `InMemoryOpeningStore` : les ouvertures, avec le contrat que réussit tout magasin | Implémenté et testé |
| Exceptions sous une seule interface `AdventCalendarException` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le branchement de la bibliothèque. |
| [Calendriers et politiques](doc/fr/calendars-and-policies.md) | Les cases, les surprises, les fuseaux horaires, les changements d'heure, les politiques d'ouverture et la fermeture. |
| [Ouvrir les cases](doc/fr/opening-boxes.md) | Ouvrir une fois, accorder la surprise, les cases qu'un membre voit, célébrer et effacer. |
| [Écrire un magasin](doc/fr/writing-a-store.md) | Le contrat qu'implémente le stockage des ouvertures d'une application. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque : le magasin, l'horloge, le répartiteur. |
| [Exceptions](doc/fr/exceptions.md) | `AdventCalendarException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation, et comment tester du code qui ouvre des cases. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/advent-calendar
```

```php
use Phil\Resource\ResourceReference;
use Symfony\Component\Clock\Clock;
use Tilttek\AdventCalendar\{AdventCalendar, Calendar, Surprise};
use Tilttek\AdventCalendar\Store\InMemoryOpeningStore;

$surprises = [];

for ($day = 1; $day <= 24; ++$day) {
    $surprises[] = 0 === $day % 6 ? Surprise::badge('advent-week') : Surprise::points(10);
}

$calendar = Calendar::of('advent-2026', 'America/Toronto', '2026-12-01', $surprises);
$advent = new AdventCalendar(new InMemoryOpeningStore(), new Clock(), $dispatcher);

$opened = $advent->open($calendar, ResourceReference::of('member', '44'), 3);
$opened->first;        // true the first time: BoxOpened was dispatched
$opened->surprise;     // Surprise::points(10)
```

## Prérequis

PHP 8.5 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
