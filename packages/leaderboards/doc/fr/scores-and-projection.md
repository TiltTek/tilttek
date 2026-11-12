<!-- phil-dev source-sha256=3193704e9179d090b8270c497cc9d73afbf84f79e0a08850d227925c759fdd1e -->
# Scores et projection

## Tenus à jour, pas recomptés à chaque affichage

`LeaderboardProjection` est une projection de `tilttek/activity` : le journal lui remet chaque activité enregistrée dont
un classement lit le verbe. Pour chaque classement auquel l'activité correspond (son verbe, ses conditions de contexte)
et dont le calendrier a une période au moment de l'activité, elle écrit une `Contribution` (un pour un `count` ou
`days`, la quantité pour un `total` ou un `best`, et ce que l'activité apporte à chaque critère de départage) et met à
jour le `Score` du membre dans cette période. Un geste enregistré en retard, un jour antérieur à celui du score dans un
classement qui compte des jours, fait recompter le score à partir de chaque contribution. Un classement affiché lit
alors les scores dans l'ordre des rangs à partir du stockage, sans lire le journal.

Une activité contribue une seule fois par classement : le stockage refuse une seconde contribution de la même activité
au même classement, et une entrée remise deux fois après une panne ne compte qu'une fois.

La contribution est ajoutée en une seule étape, mais le score est ensuite lu puis réécrit : deux activités du même membre
dans le même classement, projetées au même moment par deux processus, peuvent laisser le score sans l'une d'elles, et
une valeur rapportée par `report()` peut être écrasée par une plus ancienne. Projetez depuis un seul consommateur (la
projection lancée par Messenger ou par une commande plutôt qu'en direct dans chaque requête) ou tenez un verrou
(`symfony/lock`) par membre, classement et période autour de la projection ; une reconstruction recompte chaque score à
partir des contributions.

La projection se nomme `leaderboards` à moins que l'application ne la nomme autrement ; deux ensembles de classements
demandent deux noms.

## Fenêtres glissantes

Avec une horloge, la projection écarte un geste plus ancien qu'une fenêtre. `slide()` retire de chaque classement sur
une fenêtre les contributions plus anciennes que son premier jour au moment donné, recompte les scores qu'elles avaient
faits (un score resté sans contribution est supprimé) et retourne combien de contributions sont sorties :

```php
$projection->slide($clock->now()); // soon after midnight, from a scheduler
```

## Annulations

Une activité annulée (une commande remboursée, une partie annulée) reprend chaque contribution qu'elle a faite, et les
scores concernés sont recomptés à partir des contributions restantes : un total baisse, un meilleur score revient au
suivant, et un score resté sans contribution est supprimé.

## Reconstructions

Quand un classement change, `Projector::rebuild($projection, $since)` oublie les contributions des activités à partir
de `since` (toutes, sans date), recompte les scores qu'elles touchaient et rejoue le journal selon les nouveaux
classements. Un classement qui n'est plus déclaré perd les scores que la reconstruction a repris. Les scores d'une
mesure rapportée ne sont pas touchés : ils n'ont jamais été lus à partir du journal.

## Valeurs que rapporte l'application

Un niveau, un solde de points ou la plus longue série vit dans un autre paquet ou dans l'application. Un classement dont
la mesure est `latest` ou `highest` les prend par `Leaderboards::report()`, au moment où ils sont valides (maintenant
par défaut) et dans la période où tombe ce moment :

```php
$leaderboards->report('level', $member, 12);
```

Un classement `latest` garde la dernière valeur rapportée et ignore un rapport plus ancien que celui qu'il garde ou identique ; un
classement `highest` garde la plus haute et ignore une valeur qui ne se classe pas mieux, critères de départage compris. `report()` dit si le score a
changé. Un écouteur de l'événement du paquet qui garde le solde le rapporte : tilttek/leaderboards ne dépend d'aucun
autre paquet de ludification. Une valeur rapportée n'est pas rejouée par une reconstruction ; l'application la rapporte
de nouveau quand il le faut.

Un classement dont les critères de départage sont rapportés en prend les valeurs avec le score, une pour chacun :

```php
$leaderboards->report('level', $member, 12, tieBreaks: [340]);
```
