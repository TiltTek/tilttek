<!-- phil-dev source-sha256=e187da281cd1f0adf259c45e615eed0f0d2666f483bf53af0f9fe4baf1d1dc13 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce qu'une application fournit | Valeur par défaut |
|---|---|---|
| `Contract\ReferralStore` | L'endroit où les codes et les parrainages sont conservés | `Store\InMemoryReferralStore`, pour les tests |
| `Contract\FirstActions` | La première vraie action du nouveau | Aucune : l'application la fournit |
| `Contract\SamePerson` | Si deux comptes appartiennent à une même personne | `Contract\NoSamePersonSignal` |
| `Tilttek\Exchange\MemberSince` | La date d'arrivée d'un membre, pour les garde-fous sur les dates | `Tilttek\Exchange\NoMemberSince` |
| `Terms` | Les points, le plafond, la devise, la source et les garde-fous sur les dates | 100 et 50 points, dix récompenses |
| `Random\Randomizer` | La source des codes et des identifiants | Un `Randomizer` sur le moteur sécurisé |

La bibliothèque ne garde aucun état entre deux appels ; les implémentations qu'une application fournit n'en gardent pas
non plus.

## Le magasin

`Contract\ReferralStore` conserve les codes et les parrainages. Un magasin adossé à une base de données offre :

- une contrainte d'unicité sur le code, et une sur le membre d'un code : `addCode()` renvoie le code du membre quand il
  en a un, et null, sans rien conserver, quand le code appartient à un autre membre ;
- une contrainte d'unicité sur le nouveau d'un parrainage : `add()` renvoie le parrainage conservé pour le nouveau quand
  il y en a un ;
- `settle()` en une seule étape où un autre processus ne peut pas se glisser — une transaction qui verrouille les lignes
  du parrain, ou une seule mise à jour conditionnelle — : le parrainage passe une seule fois de en attente à récompensé,
  et les points du parrain ne comptent que tant que moins de `maxRewards` de ses parrainages lui ont donné des points,
  et jamais pour `erased:member` ;
- `refuse()`, qui fait passer une seule fois un parrainage en attente à refusé ;
- `eraseMember()`, comme le décrit [Parrainer et récompenser](referrals.md#effacer-un-membre).

`ofReferrer()` lit les parrainages du parrain, du plus ancien au plus récent, à partir d'un décalage ; `rewardedCount()`
compte ceux dont le parrain a reçu des points.

## Les premières actions

`FirstActions::firstActionOf($member)` donne la première vraie action du membre sous la forme d'une `FirstAction` : son
genre, le nom que l'application lui donne, comme `game.played` ou `sheet.published`, et son moment. L'application
répond à partir de ses propres registres : la première partie que le membre a jouée, la première fiche qu'il a publiée.
Une inscription, un profil rempli ou une adresse confirmée n'est pas une vraie action : répondez null tant que le membre
n'en a pas fait une.

## Le signal de même personne

`SamePerson::same($referrer, $newcomer)` dit si les deux comptes appartiennent à une même personne, d'après les signaux
que l'application conserve — la même adresse vérifiée, le même appareil, le même moyen de paiement dans une autre partie
du site. Elle est demandée quand le nouveau est parrainé, puis de nouveau quand la récompense est versée.
`Contract\NoSamePersonSignal` répond false : deux références sont deux personnes.

## Les dates d'arrivée

`MemberSince` de `tilttek/exchange` indique quand un membre est arrivé, ou null quand l'application ne le connaît pas.
Il n'est lu que si `Terms::$referrerSeniority` ou `Terms::$newcomerWindow` est réglé.

## Les conditions

| Condition | Valeur par défaut | Limites |
|---|---|---|
| `referrerPoints` | 100 | de 1 à 1 000 000 |
| `newcomerPoints` | 50 | de 1 à 1 000 000 |
| `maxRewards` | 10 | de 1 à 10 000 parrainages qui récompensent le parrain, au total |
| `currency` | null, la devise par défaut de `tilttek/points` | Un code de devise |
| `source` | `referrals` | Un nom de compte d'émission de `tilttek/points` |
| `referrerSeniority` | null, désactivé | Une durée ISO 8601 comme `P7D` |
| `newcomerWindow` | null, désactivé | Une durée ISO 8601 comme `P2D` |

Un changement des points s'applique aux parrainages réglés ensuite ; un parrainage déjà réglé garde ses montants.
