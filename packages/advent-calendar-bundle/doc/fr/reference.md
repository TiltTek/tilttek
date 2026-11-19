<!-- phil-dev source-sha256=6609e972e6446887f41721258e85e3b69244cd55b521edd558d1b99627eece63 -->
# Référence

Chaque classe du bundle, avec son rôle. Les services sont enregistrés sous le nom de leur classe.

## Bundle

| Classe | Rôle |
|---|---|
| `TilttekAdventCalendarBundle` | Le bundle : son arbre de configuration, l'association ORM de `CalendarRecord`, et ses services. |

## Application

| Classe | Rôle |
|---|---|
| `Application\Contract\MemberResolver` | Qui regarde le calendrier : la référence du membre courant, ou null pour un visiteur. |
| `Application\Contract\CalendarCatalogue` | D'où viennent les calendriers ; un calendrier qui n'est pas publié est introuvable. |
| `Application\Contract\MemberOpenings` | Chaque ouverture d'un membre, pour son export. |
| `Application\NoMember` | Le résolveur d'une application qui n'en nomme aucun : chaque spectateur est un visiteur. |
| `Application\Members` | Le membre courant, refusé quand la référence a l'air d'une adresse courriel. |
| `Application\CalendarEntry` | Un calendrier de la bibliothèque et son titre. |
| `Application\ConfiguredCalendars` | Les calendriers de la configuration, vérifiés à la construction du conteneur. |
| `Application\Calendars` | Les calendriers de plusieurs catalogues, interrogés dans l'ordre ; le service derrière `CalendarCatalogue`. |
| `Application\Celebrations` | Les premières ouvertures de la requête courante, que l'écran doit célébrer ; vidées après chaque requête. |
| `Application\VisitorBoxes` | Les cases telles qu'un visiteur les voit, sans aucune surprise. |

## Domaine

| Classe | Rôle |
|---|---|
| `Domain\Model\CalendarRecord` | Un calendrier que le site modifie, gardé par l'ORM ; `toCalendar()` le vérifie comme le fait la bibliothèque. |

## Infrastructure

| Classe | Rôle |
|---|---|
| `Infrastructure\Doctrine\AdventCalendarTables` | La table des ouvertures, ajoutée à un schéma. |
| `Infrastructure\Doctrine\DbalOpeningStore` | Les ouvertures sur Doctrine DBAL ; le service derrière `OpeningStore` et `MemberOpenings` avec le magasin `doctrine`. |
| `Infrastructure\Doctrine\DoctrineCalendarCatalogue` | Les calendriers de la base de données, par le gestionnaire d'entités. |
| `Infrastructure\Doctrine\OpeningsSchemaListener` | Ajoute la table des ouvertures au schéma que l'ORM génère. |
| `Infrastructure\Memory\InMemoryOpenings` | Les ouvertures en mémoire, pour les tests ; vidées après chaque requête. |
| `Infrastructure\EventListener\CelebrationListener` | Garde pour l'écran chaque première ouverture que `BoxOpened` signale. |
| `Infrastructure\Form\CalendarType` | Le formulaire d'un calendrier, sur un `CalendarRecord`. |
| `Infrastructure\Form\SurpriseType` | Le formulaire de la surprise d'une case. |
| `Infrastructure\Http\CalendarController` | L'écran d'un calendrier et l'ouverture d'une case. |
| `Infrastructure\Console\ListCalendarsCommand` | `tilttek:advent-calendar:list`. |
| `Infrastructure\Console\ShowCalendarCommand` | `tilttek:advent-calendar:show`. |
| `Infrastructure\Console\CheckCalendarsCommand` | `tilttek:advent-calendar:check`. |
| `Infrastructure\Console\Policies` | Une politique d'ouverture dans les mots des commandes. |
| `Infrastructure\Privacy\AdventCalendarPrivacy` | L'export et l'effacement d'un membre pour `phil/privacy-bundle`. |
| `Infrastructure\Twig\AdventCalendarExtension` | La fonction Twig `advent_calendar_kit()`. |
| `Infrastructure\Twig\KitComponents` | Les noms des composants du kit, après leur sous-répertoire. |
| `Infrastructure\DependencyInjection\AdventCalendarServices` | Enregistre les services que demande la configuration. |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Prend le sous-répertoire du kit dans Symfony UX Toolkit. |

## Services de la bibliothèque

| Service | Rôle |
|---|---|
| `Tilttek\AdventCalendar\AdventCalendar` | Ouvre une case pour un membre, avec l'horloge et le répartiteur d'événements de l'application. |
| `Tilttek\AdventCalendar\Contract\OpeningStore` | Le magasin des ouvertures, un alias. |

## Ressources

| Fichier | Rôle |
|---|---|
| `assets/src/boxes_controller.js` | Le contrôleur Stimulus `tilttek--advent-calendar--boxes`. |
| `assets/src/lib/boxes.js` | La lecture des réponses du serveur, testée seule. |
