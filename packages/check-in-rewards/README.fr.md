<!-- phil-dev source-sha256=ff165afed372ff7669b2aa795dd01ccf4c5ea7f2b34d1bca4d872dad4ee634cf -->
# tilttek/check-in-rewards

**Récompensez la présence depuis la porte, jamais depuis un téléphone : les lectures d'enregistrement deviennent des
passeports, des critères de chaque jour, de lieux découverts et de visites par semaine.**

tilttek/check-in-rewards transforme les admissions de `phil/checkin` en présences dans le journal de
`tilttek/activity`, où les critères de `tilttek/badges` et de `tilttek/quests` les lisent. La lecture à la porte, telle
que le registre des enregistrements la garde, est la seule source qu'il croit : aucune position déclarée par un
téléphone, aucun formulaire rempli par un membre.

`AttendanceRecorder` prend l'identifiant d'une lecture — depuis un écouteur de l'événement `CheckInRecorded` de
`phil/checkin-bundle`, ou depuis un message — et lit l'enregistrement dans le registre. Pour une admission — résultat
admis, aucun conflit entre l'appareil et le serveur —, il demande à l'application ce que l'enregistrement signifie : le
membre, la session, la table ou le stand où il est entré, l'événement, le lieu et la piste, et le fuseau horaire de
l'événement. Il enregistre l'admission une seule fois sous l'identifiant de la lecture, et avec elle la première
admission du membre à la session, ce jour-là, à ce lieu et cette semaine-là, chacune une seule fois, sous une clé tirée
de ce qui la rend unique. Un nombre d'activités est alors un nombre de sessions, de jours, de lieux et de semaines
distincts.

`AttendanceCriterion` écrit les règles qu'une application déclare — un passeport de congrès de N sessions, chaque jour
d'un événement, N lieux découverts, un lieu découvert, N semaines de visites — comme `tilttek/badges` et
`tilttek/quests` lisent leurs critères. Une série de semaines compte les semaines où un membre est venu, non les
semaines consécutives : une semaine manquée n'enlève rien.

La présence reste celle du membre. `AttendanceReader` lit la présence d'un membre pour ce membre seulement ; il n'y a
aucun classement de présence, aucune liste de qui est venu et aucun titre qui montre les habitudes de quelqu'un.
Effacer un membre par `Journal::erase()` de `tilttek/activity` efface sa présence avec le reste de son activité.

Il convient à un magasin de jeux avec un kiosque à la porte, à un congrès avec des postes d'enregistrement à ses salles
et à ses tables, à un club qui compte ses soirées. C'est une bibliothèque PHP simple dont les dépendances sont
`phil/checkin`, pour les enregistrements et leur registre, `tilttek/activity`, pour le journal, et `phil/resource`,
pour les références ; elle fonctionne avec le poste de `phil/checkin-bundle` sans en dépendre.

## État

| Partie | État |
|---|---|
| `AttendanceRecorder`, `Contract\PresenceResolver`, `Presence` : admissions enregistrées une seule fois, avec leur première session, journée, lieu et semaine | Implémenté et testé |
| `AttendanceCriterion` : sessions, chaque jour, lieux distincts, un lieu, semaines, pour les badges et les quêtes | Implémenté et testé |
| `AttendanceReader`, `AttendanceSummary` : la présence d'un membre, pour ce membre seulement | Implémenté et testé |
| Services enregistrés par un bundle | Non commencé : l'application enregistre l'enregistreur et son écouteur |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et l'appel de l'enregistreur depuis l'événement d'enregistrement. |
| [Points d'extension](doc/fr/extension-points.md) | Le résolveur, les critères et les verbes que le journal doit permettre. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, et le test du code qui récompense la présence. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |
| [Exceptions](doc/fr/exceptions.md) | Ce que signifie chaque exception, et les exceptions du journal qui parviennent à l'appelant. |

## Démarrage rapide

```php
use Tilttek\CheckInRewards\AttendanceCriterion;
use Tilttek\CheckInRewards\AttendanceRecorder;

// The recorder, on the check-in ledger and the journal of tilttek/activity, called for each scan the desk recorded.
$recorder = new AttendanceRecorder($ledger, $presences, $journal);
$dispatcher->addListener(CheckInRecorded::class, static fn (CheckInRecorded $scan) => $recorder->record($scan->id));

// A badge after five sessions of the convention, as tilttek/badges reads it.
$criterion = new CriterionReader()->read(AttendanceCriterion::sessions(5, $convention)->toBadgeCriterion());
```

## Prérequis

PHP 8.5 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
