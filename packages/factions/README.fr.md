<!-- phil-dev source-sha256=c5a459d057382f0afd173f6a47f8ef2f30b2975aa6e4233b9562a1e0193a1ec2 -->
# tilttek/factions

**Suivez où en sont le groupe et chaque personnage avec les factions d'une campagne : des paliers nommés, des rangs, des
relations entre factions, chaque changement avec sa raison.**

tilttek/factions tient les factions d'une campagne comme son maître de jeu les mène : la guilde des voleurs qui aime
bien le groupe mais déteste l'un de ses membres, le guet en guerre contre la guilde, le culte que personne à la table ne
connaît encore. Il ne connaît aucun système de jeu : la campagne choisit son échelle de positions et nomme ses paliers,
de trois pas de chaque côté jusqu'à cent.

- **Des positions pour le groupe et pour chaque personnage.** Un nombre entier sur l'échelle de la campagne, lu à travers
  le palier qui le contient. Un sujet qu'aucun changement n'a touché se tient au début de l'échelle. Un décalage déplace
  une position par pas et s'arrête aux extrémités ; une position peut aussi être fixée d'emblée.
- **Des rangs.** Une faction énumère ses rangs du plus bas au plus haut ; un personnage entre à un rang, monte, descend
  ou part. Un rang que tient un membre ne peut pas être retiré à la faction.
- **Des relations entre factions.** Alliées, amicales, neutres, rivales, hostiles ou en guerre, les mêmes dans les deux
  sens, avec une courte note, chaque paire une seule fois.
- **Chaque changement avec sa raison.** Une position, un rang ou une relation ne change qu'avec une raison — les mots du
  maître de jeu et, quand elle est nommée, la session —, conservée dans l'historique dans la même écriture que la liste.
- **Des secrets.** Une faction ou une relation peut être secrète : le maître de jeu la voit, les joueurs non. Un joueur
  voit la position du groupe et la sienne, et son propre rang, jamais ceux d'un autre personnage.
- **Borné.** 64 factions par liste, 512 relations, 16 rangs, 64 positions et 64 membres par faction, 12 paliers par
  échelle entre -100 et 100.

Le service est la seule façon de changer une liste, et seul son maître de jeu la change, lit son historique et voit ses
secrets. Chaque changement se fait sur la version lue, que le magasin conserve, puis est annoncé à un observateur, par
identifiants seulement. Le service ne garde aucun état entre les appels.

C'est une bibliothèque PHP simple : un service, des objets-valeurs, des ports et un magasin gardé en mémoire pour les
tests.

## État

| Partie | État |
|---|---|
| `Roster`, `Faction`, `Scale`, `Tier`, `Rank`, `Membership`, `Standing`, `Subject`, `Relation`, `Stance` : la liste et ses factions, bornées et vérifiées d'un bloc | Implémenté et testé |
| `Factions` : créer, ajouter, changer et retirer une faction, changer l'échelle, décaler ou fixer une position, fixer un rang, fixer et terminer des relations, céder, supprimer, lire l'historique | Implémenté et testé |
| `Reason` et l'historique : `History\StandingEntry`, `History\RankEntry`, `History\RelationEntry`, conservés avec la liste | Implémenté et testé |
| `View\RosterView` : ce que voit le maître de jeu, et ce que voit un joueur | Implémenté et testé |
| `Store\InMemoryRosterStore` : le magasin des tests | Implémenté et testé |
| Un bundle Symfony : le magasin Doctrine, l'horloge et l'observateur câblés, des pages Stimulus pour le maître de jeu et les joueurs | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [Factions et positions](doc/fr/factions.md) | La liste, l'échelle et ses paliers, les positions, les rangs, les relations, les raisons et l'historique, les secrets et les vues. |
| [Points d'extension](doc/fr/extension-points.md) | Le magasin, l'horloge, l'observateur et ses événements. |
| [Exceptions](doc/fr/exceptions.md) | `FactionsException` et ses exceptions. |
| [Tests](doc/fr/testing.md) | Les tests et les bancs d'essai de la bibliothèque. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\Factions\Faction;
use Tilttek\Factions\Factions;
use Tilttek\Factions\Rank;
use Tilttek\Factions\Reason;
use Tilttek\Factions\Relation;
use Tilttek\Factions\Roster;
use Tilttek\Factions\Scale;
use Tilttek\Factions\Stance;
use Tilttek\Factions\Subject;

$factions = new Factions(
    $store,        // your Contract\RosterStore
    $clock,        // a Psr\Clock\ClockInterface
    $observer,     // your Contract\RosterObserver, or null
);

$factions->create(new Roster('campaign-1', 'member-1', Scale::standard(), [
    new Faction('guild', 'The Thieves\' Guild', 'Runs the docks.', [new Rank('cutpurse', 'Cutpurse'), new Rank('fence', 'Fence')]),
    new Faction('watch', 'The City Watch'),
    new Faction('cult', 'The Drowned Choir', secret: true),
]));

$reason = new Reason('Returned the stolen ledger.', 'session-4');
$factions->shift('campaign-1', 'guild', Subject::party(), 1, $reason, 'member-1');
$factions->setRank('campaign-1', 'guild', 'char-2', 'cutpurse', new Reason('Sworn in at the docks.'), 'member-1');
$factions->relate('campaign-1', new Relation('guild', 'watch', Stance::Hostile, 'Bribes stopped.'), $reason, 'member-1');

$view = $factions->playerView('campaign-1', 'char-2');   // no cult; the party's standing and char-2's own
// send $view->toArray() to the page; $factions->history('campaign-1', 'guild', 'member-1') lists the changes
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-mbstring`, et `psr/clock`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
