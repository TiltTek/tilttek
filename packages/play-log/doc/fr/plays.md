<!-- phil-dev source-sha256=5d03a6ff625f5f280f0175dd0b94adb3702707009ea6e4d4d4c5e232682cb5aa -->
# Parties

## Une partie

Une `Play` est un jeu joué une fois :

| Élément | Forme |
|---|---|
| `id` | L'identifiant que choisit l'application : de 1 à 128 lettres, chiffres, « _ », « . », « : » ou « - », commençant par une lettre. |
| `game` | L'identifiant du jeu dans `tilttek/game-catalog`, de la même forme, où « @ » est aussi permis. |
| `recordedBy` | Le membre qui a enregistré la partie : lui seul la corrige ou la supprime. |
| `seats` | De 1 à 100 valeurs `Seat`, dans l'ordre donné ; un membre détient une place au plus. |
| `result` | Un `Result` : `Competitive` par défaut, `CooperativeWin`, `CooperativeLoss` ou `Unfinished`. |
| `playedAt` | Le moment où la partie a eu lieu, ou null quand la date est inconnue. |
| `minutes` | Sa durée, de 1 à 1440, ou null quand elle est inconnue. |
| `group` | L'identifiant du groupe avec lequel elle a été jouée, ou null. |
| `place` | Où elle a eu lieu : une ligne de 1 à 120 caractères, sans espaces aux extrémités, sans caractères de contrôle ni forçages de direction, ou null. |
| `version` | 0 pour une nouvelle partie ; le stockage l'augmente à chaque écriture. |

Une partie dont la date est inconnue reste sans date : on ne lui donne jamais la date de son enregistrement, et une
période l'exclut. Les identifiants sont opaques — celui d'un membre, d'un groupe ou d'une partie ne contient aucun
« @ », de sorte qu'une adresse courriel n'en est jamais un — et le lieu est le seul texte qu'une partie contient :
aucun commentaire, aucun nom de joueur.

## Places

Une `Seat` est un membre, par son identifiant, ou un joueur anonyme, sans aucun nom :

```php
new Seat('member-2', score: 37);           // a member
new Seat(score: 30, winner: true);         // an anonymous player who won
Seat::members(['member-1', 'member-2']);   // the seats of members, without scores
Seat::guests(2);                           // two anonymous seats
```

Un score est un entier de -1 000 000 000 à 1 000 000 000, ou null quand le jeu n'en compte aucun. Qui a gagné se dit
par la place et le résultat :

| Résultat | Qui a gagné |
|---|---|
| `Competitive` | Les places marquées `winner` : aucune, une ou plusieurs, quels que soient leurs scores — un jeu où le score le plus bas gagne se dit de la même façon. |
| `CooperativeWin` | Chaque place. |
| `CooperativeLoss` | Aucune place. |
| `Unfinished` | Aucune place. |

Seules les places d'une partie compétitive sont marquées comme gagnantes. `Play::won()` dit si une place a gagné et
`Play::winners()` donne les places qui ont gagné.

## Enregistrer et corriger

`PlayLog::record()` garde une nouvelle partie à la version 0, dont l'identifiant n'appartient à aucune partie gardée,
sur un jeu du catalogue qui n'est pas caché. `PlayLog::correct()` remplace une partie par sa correction, faite par le
membre qui l'a enregistrée sur la version qu'il a lue : le membre qui l'a enregistrée reste le même, et un jeu changé
pour un autre est vérifié comme à l'enregistrement. Un jeu caché dans le catalogue après l'enregistrement d'une partie
reste sur cette partie. `PlayLog::delete()` supprime une partie, par le membre qui l'a enregistrée.

## Lier les places aux membres

La place d'un membre ne compte pour lui qu'une fois confirmée. C'est le journal qui fixe la confirmation, jamais
l'appelant :

- la place du membre qui enregistre la partie est confirmée aussitôt ;
- la place d'un autre membre est confirmée quand le `Contract\PlayerConsent` de l'application dit que ce membre a
  accepté d'avance d'être lié aux parties du membre qui l'enregistre, et attend sa confirmation sinon ;
- une correction garde les places confirmées auparavant, et traite un membre qu'elle ajoute comme à l'enregistrement.

`PlayLog::confirm()` confirme la place du membre lui-même ; la confirmer de nouveau ne change rien. `PlayLog::decline()`
la rend anonyme, qu'elle ait été confirmée ou non : son score et sa victoire restent, de sorte que la partie se lit
toujours de la même façon, et elle ne compte plus pour le membre.

`Play::seenBy()` donne la partie telle qu'un lecteur la voit : le membre qui l'a enregistrée lit chaque place comme
saisie ; toute autre personne, et null pour un lecteur qui n'est pas connecté, lit anonymes les places qui ne sont pas
confirmées, sauf la sienne. Les parties qu'un membre peut ouvrir — les siennes, celles d'un groupe auquel il appartient
— sont la décision de l'application.

## Lire

`PlayLog::play()` donne une partie telle qu'elle est gardée. `PlayLog::plays()` donne les parties qu'une `PlayQuery`
retient, de 1 à 1000 à la fois, les plus récentes d'abord, celles dont la date est inconnue en dernier, puis par
identifiant :

| Requête | Les parties |
|---|---|
| `PlayQuery::ofMember($member)` | Où le membre détient une place confirmée. |
| `PlayQuery::pendingFor($member)` | Où le membre détient une place qu'il n'a pas confirmée : les parties sur lesquelles l'interroger. |
| `PlayQuery::recordedBy($member)` | Que le membre a enregistrées. |
| `PlayQuery::ofGroup($group)` | Du groupe. |

`forGame()` restreint une requête à un jeu, et `between()` à une période, de son début, inclus, à sa fin, exclue ; l'un
ou l'autre peut être ouvert. Une période exclut les parties dont la date est inconnue.

## Oublier un membre

`PlayLog::forget()` oublie un membre, jusqu'à un nombre de parties à la fois, de 1 à 1000 : elle supprime les parties
qu'il a enregistrées, puis rend anonymes ses places dans les parties des autres, confirmées ou non. Elle retourne le
nombre de parties qu'elle a supprimées ou changées ; une application l'appelle de nouveau jusqu'à ce qu'elle retourne 0,
depuis une tâche quand un membre a beaucoup de parties. Les parties qu'un autre membre a enregistrées gardent leurs
scores et leurs résultats, de sorte que ses statistiques restent justes.
