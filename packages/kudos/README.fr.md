<!-- phil-dev source-sha256=51e225f5af682e2b72001dfa5b4e7eba00815474fc0467e498832e8a195f85b6 -->
# tilttek/kudos

**Remerciements entre membres, avec des messages fixes, des plafonds quotidiens, sans compteur public ni classement.**

tilttek/kudos permet à un membre d'en remercier un autre — pour son aide, une partie bien jouée, une excellente idée —
avec l'un des messages fixes de l'application, choisi plutôt que tapé, à propos d'un objet ou non : un commentaire, un
jeu, une session. Les messages forment une liste fermée que l'application traduit et complète ; aucun texte libre n'est
jamais conservé. Par défaut, un membre donne au plus cinq remerciements par jour, comptés dans son propre fuseau
horaire, et remercie le même membre au plus trois fois en sept jours, pour que les remerciements gardent leur valeur et
que deux membres ne puissent pas se remercier en boucle. Un membre peut refuser les remerciements, de tout le monde ou
d'un seul donneur ; personne ne peut se remercier soi-même.

Ce qu'un membre a reçu n'est qu'à lui : le destinataire lit ses remerciements, le donneur lit ce qu'il a donné, et la
bibliothèque n'offre ni total des remerciements reçus, ni classement, ni lecture de ce qu'un autre membre a reçu. Elle
compte les remerciements qu'un membre a **donnés**, la mesure d'un badge pour les membres généreux.

Les remerciements sont une activité de `tilttek/activity`, enregistrée sous une clé d'idempotence — un remerciement
cliqué deux fois n'est donné qu'une fois —, retirée par une entrée à part, et effacée avec le membre par le journal. La
bibliothèque n'envoie aucune notification : elle informe les observateurs de l'application, qui relaient l'événement
s'ils le veulent.

Elle convient à une communauté qui veut de la reconnaissance entre membres sans concours de popularité. C'est une
bibliothèque PHP simple dont les dépendances sont `tilttek/activity`, `phil/resource` et `psr/clock`.

## État

| Partie | État |
|---|---|
| `Kudos` : donner, refuser, retirer, annuler, lire ce qui a été reçu et donné, compter ce qui a été donné | Implémenté et testé |
| `Message`, `Messages` : la liste fermée des messages, ses valeurs par défaut et celle de l'application | Implémenté et testé |
| `Limits` : le plafond quotidien dans le fuseau horaire du membre, et la limite par destinataire sur une période | Implémenté et testé |
| `Contract\KudosPreferences`, `Contract\MemberTimeZones`, `Contract\KudosObserver`, avec leurs valeurs par défaut | Implémenté et testé |
| Un bundle Symfony : services, traductions des messages par défaut, un contrôle Stimulus | Pas commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [Donner et lire les remerciements](doc/fr/kudos.md) | Messages, clés, refus, plafonds, lecture, retrait, effacement. |
| [Points d'extension](doc/fr/extension-points.md) | Messages, préférences, fuseaux horaires, observateurs et critère de badge. |
| [Exceptions](doc/fr/exceptions.md) | `KudosException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, et comment tester le code qui donne des remerciements. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/kudos
```

```php
use Phil\Resource\ResourceReference;
use Tilttek\Activity\Journal;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\Kudos\Kudos;

$journal = new Journal(new InMemoryActivityLog(), $clock);
$kudos = new Kudos($journal, $clock);

$kudos->give(ResourceReference::of('member', '44'), ResourceReference::of('member', '12'), 'help', ResourceReference::of('comment', '4512'));

$kudos->received(ResourceReference::of('member', '12'));   // what member 12 received, for member 12 only
$kudos->givenCount(ResourceReference::of('member', '44')); // 1
```

## Prérequis

PHP 8.5 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
