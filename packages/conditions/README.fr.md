<!-- phil-dev source-sha256=22f2c9885ffa9d2ce8a56e37d44929599c2db1ab6f29b0747cd6dcb274a08580 -->
# tilttek/conditions

**Suivez les conditions des personnages pendant le jeu : leur durée en rounds ou en temps, leur cumul, leur déclin et
ce qu'elles changent sur la fiche.**

tilttek/conditions tient les conditions des personnages d'une table — à terre, effrayé 2, trois doses de poison, une
maladie qui dure une semaine de la fiction, une mutation, un bras perdu — et dit ce qu'elles font aux statistiques. Elle
ne connaît aucun système de jeu : le site déclare les conditions de son système dans un catalogue, et la bibliothèque
applique leurs règles.

- **Un catalogue du système.** Chaque condition dit comment une nouvelle application se cumule avec celle en place
  (remplacer, garder la valeur la plus haute, additionner les valeurs, garder chaque application à part, ou ignorer),
  si elle a une valeur et jusqu'où, comment cette valeur décline au début ou à la fin des tours du personnage touché,
  les conditions qu'elle entraîne — inconscient entraîne aveuglé et à terre — et ses modificateurs.
- **Durées.** En rounds, comptés au début ou à la fin des tours du personnage touché, des tours de la source — « jusqu'à
  la fin de votre prochain tour » — ou des rounds ; en temps de jeu, écoulé quand la table fait avancer la fiction ; en
  temps réel, lu sur l'horloge, comme dans une partie en direct ; ou jusqu'au retrait.
- **Modificateurs.** Ajouter, multiplier par un pourcentage, relever à un plancher, abaisser à un plafond, ou remplacer,
  selon la valeur de la condition sur demande ; les ajouts d'un même type ne gardent que leur meilleur bonus et leur
  pire pénalité. Les statistiques sortent avec les modificateurs qui ont fait la différence, pour montrer au joueur
  pourquoi.
- **Sûre à rejouer.** Une frontière de tour ou une avance du temps porte un identifiant : annoncée deux fois, elle ne
  compte qu'une fois.

Le service lit l'heure sur une horloge PSR-20 et nulle part ailleurs. Il dit quand se termine la prochaine condition en
temps réel, pour que la tâche planifiée de l'application la termine à ce moment. Les faits sur les conditions d'un
personnage — appliquée, modifiée, terminée et pourquoi — vont à un observateur, chacun une seule fois, par identifiants
seulement.

C'est une bibliothèque PHP simple : un service, un calculateur, des objets-valeurs, des ports et un magasin gardé en
mémoire pour les tests.

## État

| Partie | État |
|---|---|
| `ConditionDefinition`, `ConditionCatalogue`, `Modifier`, `Decay`, `Duration` : les conditions du système, bornées et vérifiées | Implémenté et testé |
| `Conditions` : appliquer selon la règle de cumul, retirer, fixer une valeur, tours, rounds, temps de jeu, expiration en temps réel | Implémenté et testé |
| `Effect\EffectCalculator` : les statistiques une fois les conditions comptées, avec l'arrondi du système | Implémenté et testé |
| `View\ConditionsView` : les conditions à montrer à la table, et la prochaine fin en temps réel | Implémenté et testé |
| `Store\InMemoryConditionStore` : le magasin des tests | Implémenté et testé |
| Un bundle Symfony : le catalogue depuis la configuration, le magasin Doctrine, l'expiration planifiée et un affichage Stimulus | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le raccordement. |
| [Conditions et effets](doc/fr/conditions.md) | Le catalogue, le cumul, les valeurs et le déclin, les durées, les tours et les tics, les modificateurs et la vue. |
| [Points d'extension](doc/fr/extension-points.md) | Le magasin, l'observateur et ses événements, l'horloge, le catalogue et l'arrondi. |
| [Exceptions](doc/fr/exceptions.md) | `ConditionsException` et ses exceptions. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\Conditions\ConditionCatalogue;
use Tilttek\Conditions\ConditionDefinition;
use Tilttek\Conditions\Conditions;
use Tilttek\Conditions\Decay;
use Tilttek\Conditions\Duration;
use Tilttek\Conditions\Modifier;
use Tilttek\Conditions\Stacking;

$catalogue = new ConditionCatalogue([
    new ConditionDefinition('prone', modifiers: [Modifier::add('ac', -2)]),
    new ConditionDefinition('frightened', Stacking::Highest, maxValue: 4,
        modifiers: [Modifier::add('checks', -1, perValue: true, type: 'status')],
        decay: new Decay(1)),                                       // falls by 1 at the end of each of its turns
    new ConditionDefinition('poisoned', Stacking::Separate, maxInstances: 3,
        modifiers: [Modifier::add('str', -1)], duration: Duration::gameTime(3600)),
]);

$conditions = new Conditions($store, $catalogue, $clock, $observer);   // your ConditionStore, a PSR-20 clock, an observer

$conditions->apply('hero-1', 'frightened', 2, source: 'dragon-1');
$conditions->apply('hero-1', 'prone', duration: Duration::rounds(1));

$conditions->endTurn('enc-4:r1:hero-1:end', 'hero-1', ['hero-1', 'dragon-1']);   // frightened 1, prone ends
$conditions->advanceTime('enc-4:rest', 3600, ['hero-1']);                         // an hour of the fiction

$sheet = $conditions->effective('hero-1', ['ac' => 16, 'checks' => 5, 'str' => 3]);
// $sheet['checks']->value === 4, and $sheet['checks']->contributions says why

$view = $conditions->view('hero-1');                              // send $view->toArray() to the table
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `psr/clock`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
