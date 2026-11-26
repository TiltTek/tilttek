<!-- phil-dev source-sha256=1e341a8e3c6c0a9cde352edbb06103898497b9d359defa39f21bd16ebfc81881 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Garder les séries et les séances | Implémentez `Contract\SessionStore`, et passez-le à `SessionScheduler`. |
| Agir sur ce qui s'est passé | Implémentez `Contract\SessionObserver`, et passez-le à `SessionScheduler`. |
| Proposer des dates aux membres | Implémentez `Contract\DateProposer`, et passez-le à `SessionScheduler`. |
| Choisir des dates par un sondage de disponibilités | Écrivez un `DateProposer` qui ouvre le sondage, et rappelez le planificateur à sa fermeture. |
| Donner l'heure | Passez une `ClockInterface` PSR-20. |

## Le stockage

`Contract\SessionStore` garde les séries et les séances, chacune à une version :

- `series()` et `session()` en donnent une par son identifiant, ou null ;
- `saveSeries()` et `save()` en gardent une à la version suivante et la retournent. Une à la version 0 est nouvelle, et
  le stockage n'a aucune série ni séance de son identifiant ; toute autre n'est gardée que si le stockage la détient à
  cette version, et `ScheduleConflict` est levée sinon, rien n'étant gardé ;
- `sessions()` donne les séances qu'une `SessionQuery` accepte, au plus la limite, les plus anciennes d'abord, puis par
  identifiant. `SessionQuery::matches()` dit ce que veut dire chaque requête ; un stockage la lit à partir de `series`,
  `from`, `through` et `status`, par un index sur la série avec le début, et un sur le début ;
- `countComing()` compte les séances planifiées commençant après un moment auxquelles un membre a dit qu'il vient, dans
  toutes les séries : un stockage garde les réponses là où il peut les compter par membre.

`Store\InMemorySessionStore` les garde en mémoire, tant que l'objet dure : il sert aux tests. Une application les garde
dans sa base de données. Une série contient des chaînes, des entiers, un moment avec son fuseau horaire et sa règle, que
`(string) $rule` écrit comme le fait la RFC 5545 et que `RecurrenceRule::parse()` relit ; une séance contient des
chaînes, des entiers, un booléen, deux moments, une énumération et ses réponses par membre.

## L'observateur et ses événements

`Contract\SessionObserver::notify()` reçoit chaque fait une fois la série ou la séance gardée, par identifiants
seulement :

| Événement | Quand | Contient |
|---|---|---|
| `Event\SeriesCreated` | Une série a été créée. | |
| `Event\SeriesChanged` | Le maître de jeu a modifié une série. | |
| `Event\SessionPlanned` | Une séance a été planifiée à partir de la règle, ou ajoutée. | si elle est hors calendrier |
| `Event\SessionRescheduled` | Une séance planifiée a bougé, seule ou par un report. | si elle est hors calendrier |
| `Event\SessionCancelled` | Une séance planifiée a été annulée. | |
| `Event\SessionHeld` | Une séance a eu lieu. | |
| `Event\AttendanceConfirmed` | Un membre a dit qu'il vient. | le membre |
| `Event\AbsenceAnnounced` | Un joueur a dit qu'il est absent. | le membre, si c'est en retard |
| `Event\GameMasterAbsent` | Le maître de jeu qui dirige la séance a dit qu'il est absent. | le maître de jeu, si c'est en retard |
| `Event\SubstituteAssigned` | Un remplaçant dirige la séance, ou le maître de jeu de la série de nouveau. | le remplaçant, ou null |
| `Event\QuorumLost` | Une absence a laissé moins de joueurs que le quorum. | |
| `Event\QuorumRestored` | Un joueur qui vient a ramené la séance au quorum. | |
| `Event\DatesProposed` | Des dates ont été proposées aux membres. | la séance remplacée, ou null |

Chaque événement implémente `Event\ScheduleEvent`, dont `series` est un identifiant ; les événements d'une séance
implémentent `Event\SessionEvent`, qui ajoute `session`. Une réponse précède le quorum qu'elle change. Une application
envoie un message à partir d'eux, par Messenger, avec les identifiants seulement, et lit le reste dans le stockage : un
avis au maître de jeu sur `QuorumLost` ou `GameMasterAbsent`, un sondage après `SessionHeld`, les statistiques de la
campagne à partir des séances tenues.

## Le proposeur et un sondage de disponibilités

`Contract\DateProposer::propose()` reçoit un `DateProposal` : la série, la séance remplacée ou null, les débuts
candidats, les plus anciens d'abord, les membres sollicités (le maître de jeu qui dirige la séance, puis les joueurs) et
les minutes. Il les propose aux membres comme l'application le choisit : un sondage de disponibilités, une grille de
disponibilités, un message.

Un sondage de disponibilités, comme celui de `phil/scheduling-poll-bundle`, répond plus tard. L'adaptateur ouvre le
sondage avec les dates de la proposition comme options et retient à quoi il sert ; à la fermeture du sondage,
l'application appelle le planificateur avec la date choisie : `add()` pour une nouvelle séance, `reschedule()` pour un
remplacement. Avec un service de sondage propre à l'application, qui ouvre un sondage sur des options fixes et rappelle
à sa fermeture :

```php
use Override;
use Tilttek\SessionScheduler\Contract\DateProposer;
use Tilttek\SessionScheduler\DateProposal;

final readonly class PollDateProposer implements DateProposer
{
    public function __construct(private SchedulingPolls $polls)   // the application's poll service
    {
    }

    #[Override]
    public function propose(DateProposal $proposal): void
    {
        $this->polls->open(
            subject: $proposal->session ?? $proposal->series,  // what the poll decides, read back when it closes
            options: $proposal->candidates,                       // instants: each member answers in their own zone
            voters: $proposal->members,
            minutes: $proposal->minutes,
        );
    }
}

// When the poll closes, with the option most members answered yes or if need be:
$closed->subject === $series
    ? $scheduler->add($series, $chosen, $gameMaster)
    : $scheduler->reschedule($closed->subject, $chosen, $gameMaster);
```

Le paquet ne dépend d'aucune bibliothèque de sondage : l'adaptateur appartient à l'application.

## L'horloge

`SessionScheduler` lit l'heure dans la `ClockInterface` PSR-20 qu'on lui donne, et nulle part ailleurs : ce qui est
passé, si une absence est en retard, où la planification commence. Un test lui donne une horloge qu'il règle.
