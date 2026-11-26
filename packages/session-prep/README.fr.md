<!-- phil-dev source-sha256=f18ca21393bc9f4a42d8cdbb6d195633db7839e98cf5be8258cca9401e292196 -->
# tilttek/session-prep

**Préparer une séance de jeu : scènes, secrets et leurs indices, PNJ, lieux, notes et liste de contrôle, avec ce que la
table révèle consigné et les restes reportés.**

tilttek/session-prep garde la préparation, par le maître de jeu, d'une séance d'une campagne de jeu de rôle. Une
**préparation** contient :

- **les scènes qui pourraient avoir lieu** — un titre, ce dont le maître de jeu a besoin pour les mener, les
  personnages non joueurs et les lieux qu'elles mettent en scène — dans l'ordre que donne le maître de jeu ;
- **les secrets et leurs indices** — ce que les joueurs peuvent apprendre, lié à aucune scène pour qu'il sorte là où il
  convient, et les indices qui y mènent ;
- **les personnages non joueurs et les lieux** de la séance, chacun avec l'identifiant de sa fiche ailleurs — un wiki de
  campagne, une carte — quand il en a une ;
- **des notes libres** et **une liste de contrôle** des étapes d'une préparation, dont certaines sont exigées avant
  qu'elle soit prête. Une liste standard parcourt les étapes habituelles — le résumé, l'ouverture, les scènes, les
  secrets, les lieux, les personnages, les menaces, les récompenses —, chacune nommée par son utilité.

Une préparation est liée à une séance de l'horaire de la campagne par son identifiant seulement : la bibliothèque ne
dépend d'aucun planificateur. Chaque liste et chaque texte est borné, et chaque identifiant est vérifié.

À la table, le maître de jeu marque une scène jouée ou sautée, un secret révélé, un indice trouvé, un élément coché ;
chaque fait est conservé et annoncé une fois. Un brouillon est marqué prêt une fois ses éléments exigés cochés, puis
fermé quand la séance est terminée. Les secrets non révélés et les scènes non jouées sont reportés dans la préparation
suivante, avec les personnages et les lieux que ces scènes mettent en scène.

Les faits d'une préparation — créée, modifiée, liée à une séance, une scène, un secret, un indice ou un élément marqué,
prête, rouverte, fermée, reportée, supprimée — vont à un observateur, chacun une fois, par identifiants seulement :
l'application envoie ses messages à partir d'eux, comme un secret révélé partagé avec la connaissance qu'ont les
joueurs de la campagne.

C'est une bibliothèque PHP simple : un service, des objets-valeurs, des ports et un dépôt gardé en mémoire pour les
tests.

## État

| Partie | État |
|---|---|
| `Preparation` et ses parties : scènes, secrets et indices, personnages, lieux, notes et liste de contrôle, bornées et vérifiées | Implémenté et testé |
| `ChecklistTemplate` : les étapes standard d'une préparation, ou celles d'une application | Implémenté et testé |
| `SessionPreparer` : créer, modifier, lier à une séance, marquer les faits, rendre prête, fermer, rouvrir, reporter et supprimer | Implémenté et testé |
| `PreparationQuery`, `Contract\PreparationStore` et `Store\InMemoryPreparationStore` | Implémenté et testé |
| Un bundle Symfony : le dépôt Doctrine, Messenger, les formulaires et les composants Twig | Pas commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Préparations](doc/fr/preparations.md) | Une préparation et ses parties, leurs bornes, et comment les modifier. |
| [À la table](doc/fr/at-the-table.md) | Le service : créer, modifier, lier, marquer les faits, rendre prête, fermer, reporter. |
| [Points d'extension](doc/fr/extension-points.md) | Le dépôt, l'observateur et ses événements, les gabarits. |
| [Exceptions](doc/fr/exceptions.md) | `SessionPrepException` et ses exceptions. |
| [Tests](doc/fr/testing.md) | Les tests et les bancs d'essai de la bibliothèque. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\SessionPrep\ChecklistTemplate;
use Tilttek\SessionPrep\Clue;
use Tilttek\SessionPrep\NonPlayerCharacter;
use Tilttek\SessionPrep\Place;
use Tilttek\SessionPrep\Preparation;
use Tilttek\SessionPrep\Scene;
use Tilttek\SessionPrep\SceneStatus;
use Tilttek\SessionPrep\Secret;
use Tilttek\SessionPrep\SessionPreparer;

$preparer = new SessionPreparer($store, $observer);       // your PreparationStore, and an observer or nothing

$preparation = ChecklistTemplate::standard()->applyTo(new Preparation(
    'prep-12',
    'campaign-7',
    'member-1',                                             // the game master
    'The drowned bell',
    session: 'session-31',                                  // the session it is for, by its identifier
    scenes: [new Scene('docks', 'The docks at night', 'Fog, a bell under the water.', ['mara'], ['pier'])],
    secrets: [new Secret('heir', 'The harbour master is the heir.', [new Clue('letter', 'A letter in her desk')])],
    characters: [new NonPlayerCharacter('mara', 'Mara', 'Wants the ledger back')],
    places: [new Place('pier', 'The old pier')],
));

$preparer->create($preparation);
$preparer->markItem('prep-12', 'opening', true, 'member-1');

// At the table:
$preparer->markScene('prep-12', 'docks', SceneStatus::Played, 'member-1');
$preparer->markClue('prep-12', 'heir', 'letter', true, 'member-1');

// After the session:
$preparer->close('prep-12', 'member-1');
$preparer->carryOver('prep-12', 'prep-13', 'member-1');   // the secret, still hidden, into the next preparation
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-mbstring`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
