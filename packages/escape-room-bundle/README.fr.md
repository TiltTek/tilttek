<!-- phil-dev source-sha256=23aabf871cfa3c87246db3f35b7cd279cbcf0af9a22c3b323846b33b1374522e -->
# tilttek/escape-room-bundle

**Des escape games dans une application Symfony : la console de l'animateur, l'écran d'indices en temps réel, des séances
réservables et un classement public par consentement de l'équipe.**

tilttek/escape-room-bundle intègre `tilttek/escape-room` à Symfony. Les salles — leur scénario d'énigmes, leur durée,
leurs pénalités et la taille des équipes — s'écrivent dans la configuration, ou viennent du catalogue de l'application.
Chaque partie est gardée dans Doctrine DBAL sous forme des valeurs simples que la bibliothèque écrit, en UTC, avec le
moment où sa minuterie atteint zéro ; les résultats des équipes qui se sont échappées et ce que montre l'écran d'indices
sont gardés à côté, dans cinq tables que le bundle ajoute au schéma que génère l'ORM. `GameDesk` joue chaque coup par
l'identifiant de la partie : il lit la partie, la fait avancer, la réécrit, enregistre le résultat d'une évasion et en
informe les écrans.

L'animateur conduit une partie depuis une console — envoyer un indice, sauter une énigme, envoyer un message, effacer
l'écran, mettre en pause, reprendre, mettre fin à la partie —, chaque formulaire fonctionnant sans script et envoyé en
arrière-plan par un contrôleur Stimulus. L'écran d'indices, dans la salle, montre la minuterie, l'indice ou le message,
et célèbre une évasion avec la `Celebration` du kit PHIL UX ; il apprend chaque coup par Mercure quand un hub est là, ou
relit la partie à intervalles. Un horaire de Symfony Scheduler met fin aux parties dont la minuterie a atteint zéro, une
fois chacune. Les séances sont réservées sur `phil/reservation` quand il est installé, sous un verrou par séance dans la
base de données. Le classement public ne montre une équipe qu'une fois qu'elle y consent, et elle peut changer d'avis ;
les parties, les résultats, les écrans et les réservations d'un membre sont exportés et pseudonymisés avec son compte
par `phil/privacy-bundle`.

Il s'adresse à une application Symfony qui exploite des escape games, dans un lieu ou en ligne. La bibliothèque seule
convient à une application sans Symfony.

Il exige `tilttek/escape-room`, Doctrine DBAL, Twig et le conteneur, le noyau HTTP, le routage et le CSRF de Symfony ;
l'ORM, la console, Symfony Scheduler, Mercure, le limiteur de débit, les composants Twig, `phil/reservation` et
`phil/privacy-bundle` sont facultatifs, et le bundle n'enregistre ce qui les utilise que lorsqu'ils sont installés.

## État

| Partie | État |
|---|---|
| La configuration des salles, des magasins, de l'accès et des intégrations | Implémentée et testée |
| `GameDesk` : les coups d'une partie par son identifiant, les résultats, l'écran d'indices, les expirations, le classement | Implémentés et testés |
| `DbalGameStore`, `DbalResultStore`, `DbalScreenStore`, `DbalSeatLedger` et les cinq tables, avec l'écouteur de schéma | Implémentés et testés |
| Les magasins en mémoire pour les tests, remis à zéro entre deux requêtes d'un *worker* | Implémentés et testés |
| Les réservations sur `phil/reservation`, branchées quand il est installé | Implémentées et testées |
| L'horaire `tilttek_escape_room` sur Symfony Scheduler et la commande `tilttek:escape-room:expire` | Implémentés et testés |
| La console et l'écran d'indices, construits avec les recettes du kit, et leurs contrôleurs Stimulus | Implémentés et testés |
| Les coups dits par Mercure, le hub détecté | Implémentés et testés |
| Le consentement et le classement public | Implémentés et testés |
| L'export et l'effacement d'un membre pour `phil/privacy-bundle` | Implémentés et testés |
| Une recette Flex | Préparée dans `recipe/0.1` |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, les routes, les tables et l'accès. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Parties](doc/fr/games.md) | `GameDesk` : lancer une partie, ses coups, les résultats et le classement. |
| [Écrans](doc/fr/screens.md) | La console, l'écran d'indices, les pages du consentement et du classement ; leurs contrôleurs et leurs événements. |
| [Temps réel](doc/fr/real-time.md) | Mercure, les sujets, et les écrans sans hub. |
| [Réservations](doc/fr/bookings.md) | Les réservations des séances sur `phil/reservation`, ou celles de l'application. |
| [Planification](doc/fr/scheduling.md) | L'horaire et la commande qui mettent fin aux parties dont la minuterie a atteint zéro. |
| [Base de données](doc/fr/database.md) | Les cinq tables et ce que contient chaque colonne. |
| [Accessibilité](doc/fr/accessibility.md) | Ce que font les écrans pour chaque joueur. |
| [Vie privée](doc/fr/privacy.md) | Ce qui est exporté et pseudonymisé pour un membre. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application remplace. |
| [Référence](doc/fr/reference.md) | Les classes, les services, les routes et les exceptions. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et le test d'une application. |

## Démarrage rapide

```yaml
# config/packages/tilttek_escape_room.yaml
tilttek_escape_room:
    access: App\EscapeRoom\Access
    rooms:
        cell:
            title: The cell
            duration: 1800
            scenario: { id: cell, finale: door, puzzles: [{ id: door, title: The door, answers: [key], hints: [{ text: Under the bed }] }] }
    schedule: { enabled: true }
```

```php
$desk->start('g-01J8', 'cell', ResourceReference::of('team', 'owls'));
// The host opens /escape-room/console/g-01J8; the room shows /escape-room/screen/g-01J8.
```

## Prérequis

PHP 8.5 ou une version plus récente, Symfony 8.2, Doctrine DBAL 4.5.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
