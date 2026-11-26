<!-- phil-dev source-sha256=1c69a4f4a50aa05b6896fb1414367adaab7803554cf797fe6e0560df38957737 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce qu'une application fournit | Où |
|---|---|---|
| `Contract\PresenceResolver` | Ce qu'un enregistrement signifie : le membre, la session, l'événement, le lieu, la piste, le fuseau horaire | [Le résolveur](#le-résolveur) |
| `AttendanceCriterion` | Les règles de présence de ses badges et de ses quêtes | [Critères](#critères) |
| `AttendanceRecorder` | L'écouteur de `CheckInRecorded`, ou le gestionnaire de message, qui l'appelle avec l'identifiant de la lecture | [Installation](installation.md#brancher-lenregistreur) |
| `AttendanceVerbs::PATTERN` | Les verbes que son journal permet, quand il les déclare | [Verbes](#verbes) |

## Le résolveur

Le registre ne garde que des identifiants : la lecture, le justificatif, le point de contrôle et l'appareil.
L'application connaît le reste, et le dit par un `Contract\PresenceResolver`, qui lit l'enregistrement tel que le
registre le garde :

```php
final readonly class TicketPresences implements PresenceResolver
{
    public function resolve(CheckIn $checkIn): ?Presence
    {
        $ticket = $this->tickets->find($checkIn->credentialId);
        $session = $this->programme->atCheckpoint($checkIn->checkpoint->value);

        if (null === $ticket?->member || null === $session) {
            return null;
        }

        return new Presence(
            ResourceReference::of('member', $ticket->member),
            ResourceReference::of('session', $session->id),
            ResourceReference::of('event', $session->event),
            place: $session->room,
            track: $session->track,
            timezone: new DateTimeZone($session->timezone),
        );
    }
}
```

Il retourne null pour une admission qui ne récompense personne : un justificatif sans membre, un membre parti ou
effacé, un point de contrôle hors de tout événement. L'événement, écrit comme sa référence, le lieu et la piste
s'écrivent dans le contexte des activités, qui contient des chaînes de 1 à 100 octets ; `Presence` refuse les plus
longues avec `InvalidPresence`. Une présence sans événement — un kiosque de magasin — enregistre la session, la journée
et la semaine du membre, sans événement dans leur contexte.

Les jours et les semaines se lisent dans le fuseau horaire de la présence : une lecture à 23 h 30 à Montréal compte ce
jour-là, quel que soit le jour en UTC. Une semaine est une semaine ISO, `2027-W09`.

## Ce que l'enregistreur enregistre

Pour une admission, sous les clés ci-dessous, dans le journal :

| Verbe | Une fois par | Objet | Contexte |
|---|---|---|---|
| `attendance.recorded` | Lecture | La session | `event`, `place`, `track`, `day` |
| `attendance.session` | Membre, événement et session | L'événement | `event` |
| `attendance.day` | Membre, événement et jour | L'événement | `event`, `day` |
| `attendance.place` | Membre, événement et lieu, quand la présence a un lieu | L'événement | `event`, `place` |
| `attendance.week` | Membre et semaine | — | `week` |

La clé d'une admission est `check-in:` suivi de l'identifiant de la lecture. La clé d'une première fois est son verbe
et une empreinte SHA-256 du membre et de ce qui la rend unique, chaque partie précédée de sa longueur. Une lecture
remise, un écouteur exécuté deux fois, un message traité deux fois n'enregistrent rien de plus ; une lecture résolue
plus tard vers une autre présence que la première fois est un `IdempotencyConflict` du journal.

Un moment est le moment de la lecture, mais jamais plus tard que le moment où le serveur l'a reçue : un appareil dont
l'horloge avance ne peut pas déplacer une visite au jour suivant.

## Critères

La bibliothèque ne crée aucun badge et n'ouvre aucune quête : l'application déclare ses règles avec
`AttendanceCriterion`, et les place dans les badges et les quêtes qu'elle définit.

| Critère | Compte | Pour |
|---|---|---|
| `sessions(int $sessions, ?ResourceReference $event = null)` | Les sessions, tables ou stands distincts, de l'événement quand il est donné | Un passeport de congrès ; N parties jouées |
| `everyDay(ResourceReference $event, int $days)` | Les jours de l'événement où le membre était présent ; le nombre de jours de l'événement pour chaque jour | Présent chaque jour |
| `distinctPlaces(int $places, ?ResourceReference $event = null)` | Les lieux distincts, de l'événement quand il est donné | Un explorateur de tables ou de stands |
| `place(string $place, ?ResourceReference $event = null)` | Une admission au lieu | Une table découverte |
| `weeks(int $weeks)` | Les semaines avec au moins une visite, consécutives ou non | Un calendrier de visites sans pénalité |

`toBadgeCriterion()` écrit le critère comme le `CriterionReader` de `tilttek/badges` le lit, un `count` avec ses verbes,
son contexte et ses moments ; `toQuestStep(string $code)` l'écrit comme une étape que le `QuestReader` de
`tilttek/quests` lit :

```php
$badge = new CriterionReader()->read(AttendanceCriterion::everyDay($convention, 3)->toBadgeCriterion());

$quest = new QuestReader()->read('explorer', ['steps' => [
    AttendanceCriterion::distinctPlaces(5, $convention)->toQuestStep('tables'),
    AttendanceCriterion::place('main-stage', $convention)->toQuestStep('stage'),
]]);
```

Un compte va de 1 à 1 000 000 000 ; `InvalidCriterion` en refuse un autre, ainsi qu'un événement ou un lieu de plus de
100 octets.

Une série de semaines compte les semaines de présence, jamais les semaines consécutives : un membre qui manque une
semaine garde ce qu'il avait et continue à partir de là. Une série de jours consécutifs, qu'un jour manqué remet à
zéro, est un critère `streak` de `tilttek/badges` sur `attendance.day`, pour une application qui en veut une.

## Vie privée

`AttendanceReader::summary($viewer, $member, $event)` donne à un membre ses propres sessions, jours, lieux et semaines
distincts ; tout autre lecteur reçoit `AccessDenied`. La bibliothèque n'offre aucun classement de présence, aucune liste
des membres présents et aucun titre détenu par le visiteur le plus fréquent d'un lieu, ce qui montrerait la routine
d'une personne. Une application qui publie quelque chose sur la présence le publie à partir de ce que chaque membre a
choisi de montrer, comme les badges qu'il affiche.

Effacer un membre revient au journal : `Journal::erase()` supprime les activités du membre, présence comprise. La
bibliothèque ne garde rien en propre.

## Verbes

Les verbes de la bibliothèque sont les constantes de `AttendanceVerbs`. Un journal construit avec des verbes déclarés
refuse un verbe qu'il ne connaît pas avec `UnknownVerb` ; un tel journal déclare `AttendanceVerbs::PATTERN`,
`attendance.*`.
