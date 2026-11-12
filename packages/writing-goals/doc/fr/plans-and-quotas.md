<!-- phil-dev source-sha256=2d3ac6fa9338b5dd6ba8cb4ac2b5593b1e4b94fe64882b52876dd5b19dd3a080 -->
# Plans et quotas

## Le quota du jour

Les quotas partagent ce qui restait au début du jour entre les jours d'écriture d'aujourd'hui à l'échéance, en
proportion de leurs poids. Un objectif de 1 000 mots sur dix jours, sur une courbe linéaire, demande 100 par jour :

| Situation | Quota du jour | Les jours suivants |
|---|---|---|
| Premier jour, rien d'écrit | 100 | 100 chacun |
| Troisième jour, rien d'écrit | 125 | 125 chacun : les deux jours manqués sont passés aux huit restants |
| Deuxième jour, 280 écrits le premier jour | 80 | 80 chacun : le jour d'avance les a abaissés |

Ce qui est écrit aujourd'hui compte pour le quota du jour — `leftToday()` descend —, et remodèle les jours suivants à
partir de demain : le quota du jour ne bouge pas pendant que l'auteur écrit.

Les quotas sont des nombres entiers dont la somme est exactement ce qui reste : 100 mots sur trois jours font 33, 33 et
34. Une courbe garde sa forme quand elle se remodèle : sur un plan chargé au début, un départ manqué demande encore plus
tôt que tard.

## Le plan

`Planner::plan($goal, $progress, $today)`, ou `WritingGoals::plan($id)` au jour d'aujourd'hui de l'auteur, répond un
`Plan` :

| Membre | Contient |
|---|---|
| `written`, `writtenToday` | Ce qui a été écrit en tout, et aujourd'hui |
| `todayQuota`, `leftToday()` | Le quota du jour, 0 un jour qui n'est pas un jour d'écriture ; ce qu'il en reste |
| `quotas` | Le `DailyQuota` de chaque jour d'écriture d'aujourd'hui à l'échéance |
| `remaining()`, `percent()` | Ce qui reste de la cible ; la part écrite, de 0 à 100 |
| `isReached()` | Si la cible est atteinte |
| `isOverdue()` | Si l'échéance est passée sans que la cible soit atteinte |

Avant le premier jour, les quotas couvrent tout le plan et le quota du jour est 0. Une fois la cible atteinte, tous les
quotas restants sont 0. Passé l'échéance, il ne reste aucun quota et `remaining()` dit ce qui manque : l'auteur
enregistre l'objectif avec une échéance plus tardive, ou une cible plus basse, et le plan reprend de là. Aucune série
n'est comptée, rien n'est perdu, et un jour manqué n'est jamais montré comme un échec.
