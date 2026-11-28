<!-- phil-dev source-sha256=dca37511d22cba85d3655f1e1a7d6ac893f01170022776cc81506afc68935bd5 -->
# tilttek/downtime

**Suivez ce que font les personnages entre les séances : des activités que le système de jeu déclare, avec leurs
jours, leurs coûts et leurs issues, dans un journal par personnage.**

tilttek/downtime tient le temps entre deux séances d'une campagne. Un joueur choisit ce que fait son personnage —
fabriquer un objet, enquêter sur une rumeur, s'entraîner à une compétence, faire la fête en ville — ; le maître de jeu
fait avancer la campagne de quelques jours ; chaque activité dont les jours sont écoulés se termine par une issue, que
le maître de jeu confirme. Le paquet ne porte aucune règle d'un système de jeu : les règles d'un système déclarent les
activités, et la bibliothèque les applique.

- **Des activités déclarées comme des données.** Une activité dure de 1 à 365 jours, coûte des ressources une fois ou
  chaque jour, et se termine par l'une de ses issues : toujours la même, ou celle de la plage où tombe son jet.
  Chaque issue énumère ses effets sur les ressources que nomme le système.
- **Une file par personnage.** Jusqu'à 8 activités ouvertes par personnage, travaillées dans l'ordre où elles ont été
  choisies. Une avance dépense ses jours sur chaque file : les jours dont une activité n'a pas besoin vont à la
  suivante.
- **Des jets de la table ou d'un lanceur.** Une activité dont les jours sont écoulés se termine par son issue quand
  celle-ci est fixe, ou quand un lanceur lance ses dés ; sinon, elle attend le total obtenu à la table.
- **Le maître de jeu décide.** Il confirme l'issue, ou en choisit une autre, et peut annuler une activité ouverte ;
  un joueur annule la sienne tant qu'elle est en file. L'application débite les coûts et applique les effets quand
  elle en est avertie.
- **Un journal qui ne change pas avec les règles.** Chaque entrée garde l'activité telle qu'elle était déclarée au
  moment où elle a été choisie.
- **Borné.** 64 activités par système, 16 issues et 16 plages par activité, 8 coûts et 8 effets, 1000 entrées par
  lecture, 365 jours par avance.

Le service est la seule façon de changer une entrée. Chaque changement se fait sur la version lue, que le magasin
conserve — une avance conserve toutes les entrées qu'elle change, ou aucune —, puis est annoncé à un observateur, par
identifiants seulement. Le service ne garde aucun état entre les appels.

C'est une bibliothèque PHP simple : un service, des objets-valeurs, des ports, un livre de règles et un magasin gardé
en mémoire.

## État

| Partie | État |
|---|---|
| `Activity`, `Cost`, `Outcome`, `Effect`, `Resolution`, `Band`, `SystemRules` : les activités que déclare un système, bornées et vérifiées d'un bloc | Implémenté et testé |
| `Entry` et `Stage` : une activité dans le journal d'un personnage, de la mise en file à la confirmation ou à l'annulation | Implémenté et testé |
| `Downtime` : choisir, faire avancer une campagne, enregistrer un jet, confirmer, annuler, lire un journal | Implémenté et testé |
| `Contract\Roster`, `Contract\Roller`, `Contract\RuleBook`, `Contract\EntryStore` et `Contract\DowntimeObserver` | Implémenté et testé |
| `Rules\InMemoryRuleBook` et `Store\InMemoryEntryStore` | Implémenté et testé |
| Un bundle Symfony : le magasin Doctrine, les règles lues de la configuration, les formulaires et un journal Stimulus | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [Temps libre](doc/fr/downtime.md) | Les activités, les coûts, les issues et les résolutions, la file, les avances, les jets, la confirmation et le journal. |
| [Points d'extension](doc/fr/extension-points.md) | Le magasin, le livre de règles, le registre des rôles, le lanceur, l'observateur et ses événements. |
| [Exceptions](doc/fr/exceptions.md) | `DowntimeException` et ses exceptions. |
| [Tests](doc/fr/testing.md) | Les tests et les bancs d'essai de la bibliothèque. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\Downtime\Activity;
use Tilttek\Downtime\Band;
use Tilttek\Downtime\Cost;
use Tilttek\Downtime\Downtime;
use Tilttek\Downtime\Effect;
use Tilttek\Downtime\Outcome;
use Tilttek\Downtime\Resolution;
use Tilttek\Downtime\Rules\InMemoryRuleBook;
use Tilttek\Downtime\SystemRules;

$rules = new InMemoryRuleBook(new SystemRules('fantasy', [
    new Activity('crafting', 'Crafting', 5, [
        new Outcome('spoiled', 'The materials are spoiled'),
        new Outcome('made', 'The item is made', [new Effect('items', 1)]),
    ], Resolution::rolled('1d20+2', [new Band(1, 9, 'spoiled'), new Band(10, 30, 'made')]), [Cost::perDay('gp', 5)]),
    new Activity('training', 'Training', 10, [
        new Outcome('learned', 'A new skill', [new Effect('skills', 1)]),
    ], Resolution::fixed('learned'), [new Cost('gp', 25)]),
]));

$downtime = new Downtime(
    $store,      // your Contract\EntryStore
    $rules,      // the Rules\InMemoryRuleBook above, or your Contract\RuleBook
    $roster,     // your Contract\Roster: who runs the campaign, who plays each character
    null,        // a Contract\Roller, or null to record the rolls made at the table
    $observer,   // your Contract\DowntimeObserver, or null
);

$downtime->choose('entry-1', 'campaign-1', 'character-1', 'fantasy', 'crafting', 'member-2'); // the player
$downtime->advance('campaign-1', 7, 'member-1');                                               // the game master
$downtime->record('entry-1', 14, 'member-2');            // 14 falls in 10–30: "made"
$downtime->confirm('entry-1', 'member-1');               // the observer is told ActivityConfirmed

$log = $downtime->log('campaign-1', 'character-1', 'member-2');
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-mbstring`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
