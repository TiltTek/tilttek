<!-- phil-dev source-sha256=5802c8a59ff176c12fe66e0f3dd7d3ebc05b45ddda1e9e27192bb53968ecc394 -->
# Donner et lire les remerciements

## Donner

```php
$given = $kudos->give($giver, $receiver, 'well_played', ResourceReference::of('game', '812'));
$given->new;            // false when the same thanks were already given under the same key
$given->thanks->key;    // the idempotency key
```

`give()` reçoit le donneur et le destinataire sous forme de `ResourceReference`, un message — son nom ou un `Message`
— et un objet facultatif, ce pour quoi on remercie. Le message est cherché dans la liste de messages de l'application :
un nom hors de la liste lève `UnknownMessage`. Aucun texte du membre n'est conservé.

Le remerciement est enregistré comme une activité du verbe `kudos.given`, datée par l'horloge :

| Activité | Contient |
|---|---|
| member | le donneur |
| object | le destinataire |
| contexte `message` | le nom du message |
| contexte `object` | la référence de l'objet, 100 octets au plus, ou rien |

Un objet dont la référence dépasse 100 octets lève `InvalidKudos`.

## Clés

Le remerciement est enregistré une fois par clé d'idempotence. Donné de nouveau sous la même clé, `give()` retourne le
remerciement déjà donné, avec `new` à faux : il ne refuse rien, même si un plafond a été atteint depuis, et n'apprend
rien aux observateurs. La même clé donnée à un autre remerciement lève l'`IdempotencyConflict` de `tilttek/activity`.

L'application peut passer sa propre clé — l'identifiant de la requête, par exemple. Sans clé, la clé est `kudos:` suivi
d'un SHA-256 du donneur, du destinataire, du message, de l'objet et de la journée du donneur : le même remerciement
cliqué deux fois dans une journée n'est donné qu'une fois, et donné de nouveau le lendemain.

## Refus

Les remerciements sont refusés par `KudosRefused`, dont `refusal` dit pourquoi, vérifié dans cet ordre :

| `Refusal` | Quand |
|---|---|
| `Self` | Le donneur et le destinataire sont le même membre. |
| `Declined` | Les préférences du destinataire refusent les remerciements de ce donneur. |
| `DailyCap` | Le donneur a donné `Limits::$daily` remerciements aujourd'hui — cinq par défaut. |
| `PairLimit` | Le donneur a remercié ce destinataire `Limits::$perReceiver` fois dans `Limits::$period` — trois en sept jours par défaut. |

`refusal($giver, $receiver)` donne la raison sans rien donner, ou null, et `remainingToday($giver)` les remerciements
que le donneur peut encore donner aujourd'hui : un écran les lit pour offrir le bouton ou dire pourquoi il n'est pas
offert.

La journée commence à minuit dans le fuseau horaire du donneur, donné par `Contract\MemberTimeZones`, lu à l'horloge.
La période de la limite par destinataire finit maintenant et commence `period` plus tôt, inclus. Les remerciements
retirés comptent encore dans les deux, pour qu'on ne puisse pas donner et retirer sans fin.

Deux requêtes du même donneur au même instant peuvent toutes deux passer les vérifications : une application qui doit
tenir le plafond malgré cette concurrence prend un verrou par donneur autour de `give()`, avec `symfony/lock` par
exemple.

## Lire

| Méthode | Donne |
|---|---|
| `received($member, $after, $limit)` | Les remerciements que le membre a reçus |
| `given($member, $after, $limit)` | Les remerciements que le membre a donnés |
| `givenCount($member, $since, $until)` | Combien de remerciements le membre a donnés dans la période |

`received()` et `given()` retournent une `ThanksPage` : jusqu'à `$limit` entrées du journal lues après la position
`$after` — 50 par défaut, de 1 à 100 —, les plus anciennes d'abord, sans les remerciements retirés, et la position après
laquelle lire la page suivante, null quand le journal n'en contient plus. Une page peut contenir moins de remerciements
que sa limite, puisque les remerciements retirés et leurs annulations prennent leur place dans la lecture.

Ce qu'un membre a reçu n'est qu'à lui : l'application appelle `received()` avec le membre connecté seulement. La
bibliothèque n'offre ni total des remerciements reçus, ni classement, ni autre lecture de ce qu'un membre a reçu. Les
remerciements n'affichent aucun décompte sur un profil, et un destinataire qui veut les partager le fait par
l'application.

`givenCount()` compte les remerciements donnés, sans les remerciements retirés, de `$since`, inclus, à `$until`, exclu :
la mesure d'un badge pour les membres généreux ([Points d'extension](extension-points.md#le-critère-de-badge)).

## Retirer et annuler

`withdraw($giver, $key)` retire un remerciement que le donneur a donné ; `cancel($key)` supprime un remerciement, quel
que soit son donneur, pour un modérateur. Chacun retourne faux quand la clé n'enregistre aucun remerciement — ou, pour
`withdraw()`, le remerciement d'un autre donneur — et vrai une fois le remerciement annulé, la première fois et chaque
fois après.

Une annulation est une entrée à part du journal, telle que `tilttek/activity` l'écrit : les remerciements ne sont
jamais réécrits. Les observateurs ne sont informés que de la première annulation.

## Effacer un membre

Un membre est effacé par le journal, avec `Journal::erase()` de `tilttek/activity` : les remerciements qu'il a donnés
sont supprimés, et ceux qu'il a reçus sont gardés pour leurs donneurs, le destinataire étant oublié —
`Thanks::$receiver` est null. Un objet pour lequel on a remercié, s'il est lui-même une référence de membre, reste dans
le contexte ; l'application efface ce qu'elle a dérivé des remerciements, comme les notifications qu'elle a envoyées.

## Événements

`Contract\KudosObserver` est informé une seule fois de `KudosGiven` et de `KudosCancelled`, après que le journal a écrit
l'entrée. Chaque événement porte des identifiants seulement — la clé, le donneur, le destinataire, le nom du message,
l'objet —, que l'application relaie comme notification ou comme message sur son bus. La bibliothèque n'envoie elle-même
aucune notification.
