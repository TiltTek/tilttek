<!-- phil-dev source-sha256=12215389e94ecdc71cc78763c6562c184825dd6545c1ca592fe4e54934511b04 -->
# tilttek/session-scheduler

**Gardez les séances d'une campagne sur la bonne voie : dates récurrentes planifiées par lots, absences avec préavis, reports, maîtres de jeu remplaçants et rappels.**

tilttek/session-scheduler tient les séances d'une campagne de jeu de rôle. Une **série** contient :

- **quand** : la première séance, dans son fuseau horaire, et une règle iCalendar (RFC 5545) lue par `phil/recurrence` :
  chaque vendredi, un dimanche sur deux, le premier samedi du mois. Les séances gardent l'heure de la première sur
  l'horloge murale de leur fuseau horaire, malgré les changements d'heure ;
- **qui** : le maître de jeu, qui modifie la série et ses séances, et jusqu'à 50 joueurs ;
- **les réglages de la table** : la durée d'une séance, le quorum de joueurs sous lequel une séance ne vaut pas la
  peine d'être tenue, le préavis avec lequel une absence est annoncée (24 heures par défaut), et un rappel avant chaque
  séance, ou aucun.

Le maître de jeu planifie les séances de la règle par lots, jusqu'à 100 à la fois ; planifier de nouveau ne planifie que
ce qui est nouveau. Une séance peut être ajoutée à n'importe quelle heure de début : celle qui n'est pas une occurrence
de la règle est marquée hors calendrier, jamais refusée. Une séance est replanifiée, reportée avec toutes les séances
suivantes du même nombre de jours pour que leur espacement reste, annulée, confiée à un maître de jeu remplaçant, ou
marquée tenue une fois commencée.

Chaque joueur dit qu'il vient ou qu'il est absent. Une absence annoncée après le préavis est tout de même enregistrée,
marquée en retard, pour que le maître de jeu en décide ; quand des absences laissent moins de joueurs que le quorum, le
maître de jeu en est informé, et la séance n'est jamais annulée d'elle-même. Une application peut plafonner les
séances à venir auxquelles un membre dit qu'il vient.

Les dates sont proposées aux membres par un port : une première date, ou des jours libres à l'heure de la série pour
remplacer une séance sautée. Un sondage de disponibilités répond par un adaptateur que l'application écrit, décrit dans
les [points d'extension](doc/fr/extension-points.md). Les rappels dus dans une fenêtre sont listés pour que la tâche
planifiée de l'application les envoie.

Les faits d'une série (créée, modifiée, une séance planifiée, replanifiée, annulée ou tenue, un membre qui vient ou qui
est absent, le maître de jeu absent, un remplaçant assigné, le quorum perdu ou rétabli, des dates proposées) vont à un
observateur, chacun une fois, par identifiants seulement.

C'est une bibliothèque PHP simple : un service, des objets-valeurs, des ports et un stockage gardé en mémoire pour les
tests.

## État

| Partie | État |
|---|---|
| `Series`, `Session`, `Reply`, `SessionStatus` : une série et ses séances, bornées et vérifiées | Implémenté et testé |
| `SessionScheduler` : séries, planification par lots, séances ajoutées hors calendrier, replanification, report, annulation, tenue, remplaçants | Implémenté et testé |
| Présence : vient, absent avec préavis ou en retard, le quorum, un plafond de séances à venir par membre | Implémenté et testé |
| `DateProposal` et `Contract\DateProposer` : des dates pour une première séance ou un remplacement | Implémenté et testé |
| `Reminder` : les rappels dus dans une fenêtre | Implémenté et testé |
| `Store\InMemorySessionStore` : le stockage des tests | Implémenté et testé |
| Un bundle Symfony : le stockage Doctrine, Messenger, les rappels planifiés, les formulaires et les composants Twig | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Séries et séances](doc/fr/sessions.md) | Séries, planification, ajout, replanification, report, annulation, tenue et remplaçants. |
| [Présence et rappels](doc/fr/attendance.md) | Vient et absent, le préavis, le quorum, le plafond, les propositions et les rappels. |
| [Points d'extension](doc/fr/extension-points.md) | Le stockage, l'observateur et ses événements, le proposeur et l'adaptateur d'un sondage de disponibilités. |
| [Exceptions](doc/fr/exceptions.md) | `SchedulerException` et ses exceptions. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Phil\Recurrence\RecurrenceRule;
use Tilttek\SessionScheduler\Series;
use Tilttek\SessionScheduler\SessionScheduler;

$scheduler = new SessionScheduler($store, $clock, $observer, $proposer);   // your SessionStore, a PSR-20 clock, ports

$scheduler->createSeries(new Series(
    'iron-coast',
    'campaign-7',
    'member-1',                                                   // the game master
    new DateTimeImmutable('2027-03-05 19:00', new DateTimeZone('America/Toronto')),
    RecurrenceRule::parse('FREQ=WEEKLY;BYDAY=FR'),
    ['member-2', 'member-3', 'member-4'],
    minutes: 240,
    quorum: 2,
    reminder: 6,                                                  // six hours before each session
));

$sessions = $scheduler->plan('iron-coast', new DateTimeImmutable('2027-06-30'), 'member-1');

$scheduler->absent($sessions[0]->id, 'member-3');                 // late after the 24 hours of notice, still recorded
$scheduler->postpone($sessions[1]->id, 7, 'member-1');            // this session and every later one, a week later
$scheduler->proposeReplacement($sessions[2]->id, new DateTimeImmutable('2027-04-15'), 'member-1');

foreach ($scheduler->reminders($previousRun, $now) as $reminder) {
    // send a message to $reminder->members for $reminder->session
}
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `phil/recurrence` et `psr/clock`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
