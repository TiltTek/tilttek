<!-- phil-dev source-sha256=806d3eb30e6d48078703dffdfd3371af69e93a9274b846bbd232b690602ff07b -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente.
- `phil/checkin`, dont le registre garde les enregistrements ; avec `phil/checkin-bundle`, dont les points de
  terminaison répartissent `CheckInRecorded`, pour un poste.
- `tilttek/activity`, dont le journal enregistre la présence.

## Installer

```sh
composer require tilttek/check-in-rewards
```

## Brancher l'enregistreur

La bibliothèque n'enregistre aucun service et ne dépend d'aucun bundle : l'application enregistre `AttendanceRecorder`
avec trois services qu'elle a déjà — le registre des enregistrements, son résolveur de présences et le journal —, et
l'appelle depuis son écouteur de `Phil\CheckinBundle\Application\Event\CheckInRecorded` avec l'identifiant de la
lecture.

```yaml
# config/services.yaml
services:
    App\Attendance\TicketPresences: ~

    Tilttek\CheckInRewards\AttendanceRecorder:
        arguments:
            $ledger: '@Phil\Checkin\Contract\CheckInLedger'
            $resolver: '@App\Attendance\TicketPresences'
            $journal: '@Tilttek\Activity\Journal'

    Tilttek\CheckInRewards\AttendanceReader:
        arguments: ['@Tilttek\Activity\Journal']
```

```php
#[AsEventListener]
final readonly class RewardAttendance
{
    public function __construct(
        private AttendanceRecorder $recorder,
    ) {
    }

    public function __invoke(CheckInRecorded $scan): void
    {
        $this->recorder->record($scan->id);
    }
}
```

L'enregistreur lit le résultat, le conflit et le moment dans le registre, jamais dans l'événement : ce qui l'appelle lui
remet un identifiant, rien qu'il pourrait inventer.

L'enregistreur et le lecteur ne gardent rien entre deux appels : une seule instance sert toutes les requêtes, dans un
*worker* comme dans une requête.

Appelé depuis l'écouteur, l'enregistreur enregistre dans le même processus que la lecture. Une application qui
récompense la présence plus tard — le poste répond d'abord — envoie un message de Messenger qui porte l'identifiant de
la lecture, et son *handler* appelle l'enregistreur avec lui ; l'identifiant de la lecture garde la présence comptée une
seule fois, peu importe le nombre de fois que le message est traité.

## Le journal

Le journal enregistre une activité datée dans sa fenêtre, trente jours avant son horloge par défaut. Une lecture faite
hors ligne et synchronisée plus tard que cela est refusée par le journal : une application dont les postes restent hors
ligne plus longtemps donne au journal une fenêtre plus large.

Un journal qui déclare ses verbes déclare `attendance.*` avec eux, `AttendanceVerbs::PATTERN`.
