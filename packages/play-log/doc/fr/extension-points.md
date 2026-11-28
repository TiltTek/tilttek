<!-- phil-dev source-sha256=1389ebe9c12480074f8a3c55005755823d5b46dd4cee1c0fe2b0cd4cf458b1d9 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Garder les parties | Implémentez `Contract\PlayStore`, et passez-le à `PlayLog` et à `PlayStatistics`. |
| Lier les membres qui ont accepté d'avance | Implémentez `Contract\PlayerConsent`, et passez-le à `PlayLog`. |
| Agir sur ce qui s'est passé | Implémentez `Contract\PlayObserver`, et passez-le à `PlayLog`. |
| Recevoir les parties d'une soirée de jeux | Adaptez le port de la soirée de jeux à `PlayLog::record()`. |
| Donner au catalogue l'expérience d'un critique | Adaptez `PlayStatistics` au `Contract\PlayHistory` du catalogue. |

## Le stockage

`Contract\PlayStore` garde les parties, chacune à une version :

- `play()` donne une partie par son identifiant, ou null ;
- `save()` garde une partie à la version suivante et la retourne. Une partie à la version 0 est nouvelle, et le stockage
  n'en a aucune de son identifiant ; toute autre n'est gardée que si le stockage la détient à cette version, et
  `PlayConflict` est levée sinon, sans que rien soit gardé ;
- `delete()` supprime une partie que le stockage détient à cette version, et lève `PlayConflict` sinon ;
- `plays()` donne les parties qu'une `PlayQuery` retient, au plus la limite, les plus récentes d'abord, celles dont la
  date est inconnue en dernier, puis par identifiant. `PlayQuery::matches()` énonce ce que veut dire chaque requête ; un
  stockage la lit à partir de `kind`, `subject`, `game`, `from` et `until`, par un index sur les membres des places
  confirmées, les membres des places en attente, le membre qui a enregistré la partie et le groupe, chacun avec la
  date.

`Store\InMemoryPlayStore` garde les parties en mémoire, aussi longtemps que l'objet vit : il sert aux tests. Une
application les garde dans sa base de données ; une partie contient des chaînes, des entiers, des booléens, une date et
une énumération, et ses places gardent leur ordre.

## Le consentement

`Contract\PlayerConsent::allows()` dit si un membre a accepté d'avance d'être lié aux parties qu'enregistre un autre
membre : ils sont amis, il a rejoint un groupe dont les parties lient ses membres, ou un réglage de son compte le dit.
Il est interrogé pour chaque place d'un autre membre, quand une partie est enregistrée ou qu'une correction ajoute le
membre. Sans consentement, chaque place d'un autre membre attend que ce membre la confirme.

## L'observateur et ses événements

`Contract\PlayObserver::notify()` est informé de chaque fait une fois la partie gardée, par identifiants seulement :

| Événement | Quand | Porte |
|---|---|---|
| `Event\PlayRecorded` | Une partie a été enregistrée. | le membre qui l'a enregistrée |
| `Event\PlayCorrected` | Le membre qui a enregistré une partie l'a corrigée. | le membre qui l'a enregistrée |
| `Event\PlayDeleted` | Le membre qui a enregistré une partie l'a supprimée, ou il a été oublié. | le membre qui l'a enregistrée |
| `Event\SeatLinked` | La place d'un membre a été confirmée — par lui, par son consentement, ou comme membre qui l'a enregistrée. | le membre |
| `Event\SeatPending` | Un membre a reçu une place qu'il n'a pas confirmée : le moment de l'interroger. | le membre |
| `Event\SeatUnlinked` | La place confirmée d'un membre ne compte plus pour lui : refusée, retirée par une correction, ou sa partie supprimée. | le membre |

Chaque événement implémente `Event\PlayEvent`, dont `play` et `game` sont des identifiants. Le fait de la partie vient
d'abord, puis les places liées, en attente et déliées, chacune dans l'ordre des places. Une application en tire un
message — par Messenger, avec les identifiants seuls — et lit le reste dans le journal.

`SeatLinked` et `SeatUnlinked` sont ce que compte un journal d'activité, comme `tilttek/activity` : un observateur
enregistre une activité du membre sur le jeu pour `SeatLinked`, avec la partie et le membre comme clé d'idempotence, et
l'annule pour `SeatUnlinked`. Le paquet ne dépend d'aucune bibliothèque de ce genre.

## Les parties d'une soirée de jeux

Une bibliothèque de soirées de jeux qui envoie les jeux joués à ses tables par un port à elle rejoint le journal par un
adaptateur que l'application écrit. Pour `tilttek/game-night`, dont le `Contract\PlayJournal` reçoit des valeurs
`PlayedGame` — la soirée, la table, le jeu, les membres, les invités sous forme de nombre, le moment et les minutes :

```php
use Override;
use Tilttek\GameNight\Contract\PlayJournal;
use Tilttek\GameNight\PlayedGame;
use Tilttek\PlayLog\Play;
use Tilttek\PlayLog\PlayLog;
use Tilttek\PlayLog\Seat;

final readonly class NightPlayJournal implements PlayJournal
{
    public function __construct(private PlayLog $log)
    {
    }

    #[Override]
    public function record(PlayedGame $play): void
    {
        $this->log->record(new Play(
            'night-'.hash('xxh128', implode('|', [$play->night, $play->table, $play->game, $play->playedAt->format('U.u')])),
            $play->game,
            $play->players[0],
            [...Seat::members($play->players), ...Seat::guests($play->guests)],
            playedAt: $play->playedAt,
            minutes: $play->minutes,
        ));
    }
}
```

Les deux bibliothèques prennent les identifiants du catalogue pour les jeux et ceux des membres sans « @ », et les
bornes d'un `PlayedGame` — 30 places, de 1 à 1440 minutes — tiennent dans celles d'une `Play` : les valeurs passent
telles quelles. Les invités deviennent des places anonymes. L'adaptateur décide qui enregistre la partie — ici le
premier membre de la table, dont la place est liée aussitôt ; les autres confirment la leur, ou sont liés par le
consentement, puisqu'une soirée où ils étaient présents est le genre d'accord qu'un `PlayerConsent` peut lire — et à
quel groupe elle appartient, quand l'application garde le groupe d'une soirée. L'identifiant fait de la soirée, de la
table, du jeu et du moment donne le même identifiant à une partie enregistrée deux fois, que le journal refuse avec
`PlayRefused`.

## L'historique de parties du catalogue

`tilttek/game-catalog` demande à un `Contract\PlayHistory` combien de parties d'une entrée un membre a jouées, pour
montrer l'expérience d'un critique. Un adaptateur la lit dans les statistiques :

```php
public function played(string $member, string $entry): int
{
    return $this->statistics->ofMember($member)->game($entry)?->plays ?? 0;
}
```

Le journal ne connaît aucun meneur de jeu : `ran()` vient de l'endroit où l'application enregistre qui a mené une
partie, ou vaut 0.

## Sûr avec les workers

Les services et tout ce qu'ils utilisent ne gardent rien entre les appels : ils peuvent vivre aussi longtemps qu'un
worker, et servir plusieurs sites d'une même application à la fois, le stockage décidant des parties qu'il lit.
