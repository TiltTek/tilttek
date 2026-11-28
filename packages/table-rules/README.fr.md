<!-- phil-dev source-sha256=ec12d7b9d19936f4a1a4ba59353db321abe45bb77a84e614fa2c3ed78b7a0b35 -->
# tilttek/table-rules

**Gardez les règles maison d'une table versionnées, acceptées par chaque joueur et acceptées de nouveau quand elles
changent, avec ce qui a changé d'une version à l'autre ; neutre quant au système de jeu.**

tilttek/table-rules garde les règles maison d'une table ou d'une campagne — pas de téléphone à la table, la façon de
lancer les dés, ce qui arrive quand un joueur est absent — et les variantes de règles du système de jeu auquel la
table joue. Une règle est un titre et un texte ; une variante est un identifiant, vérifié contre les variantes de règles
des systèmes de `tilttek/rpg-systems`, ou contre un catalogue propre à l'application, ou pris tel qu'il est donné.

- **Versions.** Chaque publication est une nouvelle version numérotée qui fait foi dès lors, jamais modifiée ensuite,
  avec une note sur ce qui a changé. Publier les règles qui font déjà foi est refusé.
- **Acceptation explicite.** Chaque joueur accepte ou refuse la version qui fait foi ; être à la table n'accepte rien.
  Un joueur répond à la version qu'il a lue : si une autre a été publiée entre-temps, la réponse est refusée.
- **Nouvelle acceptation.** Une version qui change les règles demande de nouveau à chaque joueur ; une version qui
  corrige seulement une coquille ou précise une phrase est publiée sans rien demander, et les acceptations des
  versions précédentes tiennent toujours.
- **Ce qui a changé.** Entre deux versions quelconques : les règles ajoutées, retirées, déplacées et modifiées, ligne
  par ligne dans un texte, les variantes choisies et abandonnées, et le système de jeu avant et après. Un joueur dont
  l'acceptation est périmée voit ce qui a changé depuis la version qu'il a acceptée.
- **Où en est chacun.** Pour une liste de joueurs : qui a accepté, qui a refusé, qui n'a pas encore répondu, et qui a
  accepté une version antérieure.

Le service lit l'heure d'une horloge PSR-20 et de rien d'autre. Les faits des règles d'une table — publiée, acceptée,
refusée, oubliée — vont à un observateur, chacun une seule fois, par identifiants seulement.

C'est une bibliothèque PHP simple : un service, un comparateur, des objets-valeurs, des ports et un magasin gardé en
mémoire pour les tests.

## État

| Partie | État |
|---|---|
| `Rule`, `RulebookVersion`, `Rulebook` : les règles, leurs versions et les décisions, bornées et vérifiées | Implémenté et testé |
| `TableRules` : publier, accepter, refuser, oublier, état, liste et comparaison | Implémenté et testé |
| `Diff\RulebookDiffer` : ce qui a changé entre deux versions, règle par règle et ligne par ligne | Implémenté et testé |
| `View\PlayerStatus`, `View\RosterView` : où en sont les joueurs, en données simples | Implémenté et testé |
| `Catalogue\SystemVariantCatalogue` : les variantes de règles des systèmes de `tilttek/rpg-systems` | Implémenté et testé |
| `Store\InMemoryRulebookStore` : le magasin des tests | Implémenté et testé |
| Un bundle Symfony : le magasin Doctrine, les messages Messenger et un affichage Stimulus des changements | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le branchement. |
| [Règles, versions et acceptation](doc/fr/rules.md) | Les règles, les versions, les variantes, l'acceptation et la nouvelle acceptation, la comparaison et les vues. |
| [Points d'extension](doc/fr/extension-points.md) | Le magasin, l'observateur et ses événements, l'horloge et le catalogue de variantes. |
| [Exceptions](doc/fr/exceptions.md) | `TableRulesException` et ses exceptions. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\TableRules\Rule;
use Tilttek\TableRules\TableRules;

$rules = new TableRules($store, $clock, $observer);   // your RulebookStore, a PSR-20 clock, an observer

$rules->publish('campaign-7', 'gm-1', [
    new Rule('phones', 'Phones', 'Phones stay in the bag during the session.', 'table'),
    new Rule('dice', 'Dice', 'Roll in the open; a die off the table is rolled again.'),
], variants: ['flanking'], system: 'srd-5', note: 'The first rules of the campaign.');

$rules->accept('campaign-7', 'player-3', 1);           // the version the player read

$rules->publish('campaign-7', 'gm-1', [
    new Rule('phones', 'Phones', "Phones stay in the bag during the session.\nOn call? Tell the table."),
    new Rule('dice', 'Dice', 'Roll in the open; a die off the table is rolled again.'),
]);                                                     // asks every player again

$status = $rules->status('campaign-7', 'player-3');
// $status->state === AcceptanceState::Outdated, and $status->changes says what changed since version 1

$roster = $rules->roster('campaign-7', ['player-3', 'player-4']);   // send $roster->toArray() to the game master
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `psr/clock` et `tilttek/rpg-systems`, dont les systèmes donnent les variantes de règles.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
