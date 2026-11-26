<!-- phil-dev source-sha256=ca76592bb538421f352562bb0e5c26c0f5dfebff92adf4e8049894031435e8d9 -->
# Parrainer et récompenser

## Le code

`code($member)` donne le code de parrainage du membre, tiré la première fois qu'on le demande et conservé ensuite : dix
caractères en base 32 de Crockford — des chiffres et des lettres majuscules sans I, L, O ni U —, 50 bits aléatoires qui
ne disent rien du membre. L'application le met dans le lien que le membre partage, et le relit depuis ce lien ou depuis
un formulaire :

```php
$code = $referrals->code($member);
$link = $urlGenerator->generate('join', ['ref' => $code->value]);
```

Un code se relit quelle que soit sa casse, avec I et L pour 1, O pour 0 et les traits d'union omis, comme une personne
le recopie. Quand le code tiré appartient déjà à un autre membre, un autre est tiré, cinq fois au plus.

## Le parrainage

`refer($code, $newcomer)` enregistre que le nouveau s'est joint par le code, au moment de l'horloge. Les garde-fous le
refusent, chacun avec un `Refusal` :

| Refus | Quand |
|---|---|
| `SelfReferral` | Le code est celui du nouveau, ou `Contract\SamePerson` indique que les deux comptes sont une même personne |
| `AlreadyReferred` | Le nouveau a été parrainé par un autre membre |
| `NewMember` | `Terms::$referrerSeniority` est réglé, et le parrain est arrivé plus récemment que cela, ou est inconnu |
| `NotNewcomer` | `Terms::$newcomerWindow` est réglé, et le nouveau est arrivé il y a plus longtemps que cela, ou est inconnu |

Le même nouveau parrainé de nouveau par le même code est le même parrainage, renvoyé sans revérifier les dates. Un
nouveau est parrainé une seule fois : le magasin conserve un parrainage par nouveau, même quand deux processus en
enregistrent deux au même moment, et le second est refusé comme `AlreadyReferred`.

## La récompense

`reward($newcomer)` verse le parrainage du nouveau. L'application l'appelle quand le nouveau agit — une partie jouée,
une fiche publiée —, et la bibliothèque demande à `Contract\FirstActions` la première vraie action du nouveau plutôt
que de se fier à l'appel : sans action, ou avec une action faite avant le parrainage, la récompense est refusée comme
`NoFirstAction`, et le parrainage reste en attente jusqu'à un appel ultérieur.

Quand `Contract\SamePerson` indique maintenant que les deux comptes sont une même personne — un signal que l'application
a appris depuis le parrainage, comme la même adresse vérifiée —, le parrainage est refusé pour de bon, et chaque appel
ultérieur est refusé comme `SelfReferral`.

Sinon, le magasin règle le parrainage comme récompensé, en une seule étape : le nouveau reçoit `Terms::$newcomerPoints`,
et le parrain `Terms::$referrerPoints` tant que moins de `Terms::$maxRewards` de ses parrainages lui ont donné des
points. Le parrainage garde les deux montants, de sorte qu'une récompense redemandée verse la même chose, quelles que
soient les conditions devenues entre-temps.

Les points sont accordés par `Points::grant()`, depuis le compte d'émission `Terms::$source` — `referrals` par
défaut — dans `Terms::$currency`, sous deux clés construites à partir de l'identifiant aléatoire du parrainage :

```text
referral:6f1c…e2:newcomer
referral:6f1c…e2:referrer
```

Le grand livre écrit une clé une seule fois, de sorte que la récompense redemandée, ou demandée par deux processus au
même moment, ne donne rien de plus : `Reward::$new` indique si cet appel a écrit un mouvement. Un paiement interrompu
entre le règlement et les octrois est terminé par l'appel suivant. Un membre qui n'a pas été parrainé n'a pas de
récompense : `reward()` renvoie null.

## Le plafond

Le plafond compte les parrainages qui ont donné des points au parrain, au total. Il tient quand deux nouveaux du même
parrain agissent au même moment, puisque le magasin vérifie et règle en une seule étape. Au-delà, le nouveau reçoit
tout de même ses points ; ceux du parrain sont 0. `remaining($referrer)` indique combien de parrainages de plus peuvent
le récompenser.

## La lecture

`referralOf($newcomer)` donne le parrainage du nouveau ; `referralsOf($referrer, $offset, $limit)` ceux du parrain, du
plus ancien au plus récent, au plus 100 à la fois. Un `Referral` contient son statut — `Pending`, `Rewarded` ou
`Refused` —, ses moments en UTC, et les points que chaque côté a reçus.

## Effacer un membre

`erase($member)` ne laisse aucun code ni aucun parrainage qui relie le membre effacé à quelqu'un :

- son code est supprimé ;
- son parrainage en attente comme nouveau est supprimé ;
- dans ses autres parrainages, d'un côté comme de l'autre, `erased:member` prend sa place : un parrainage récompensé
  compte toujours dans le plafond de son parrain, et un parrainage en attente récompense toujours son nouveau, sans
  aucun point pour le parrain effacé.

Les clés des mouvements portent l'identifiant aléatoire du parrainage, jamais un membre : une fois que le parrainage
nomme `erased:member`, le grand livre ne relie personne. Les points eux-mêmes sont effacés par `Points::erase()`, que
l'application appelle aussi.
